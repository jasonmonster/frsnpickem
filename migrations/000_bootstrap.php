<?php
/**
 * One-shot local setup — run this instead of the `mysql` CLI (DBngin doesn't
 * ship one). Connects as your MySQL root user, creates the pickem database
 * and app user, loads the schema, seeds the NFL teams (if the frsn assets
 * folder is where we expect), creates the active season, and writes .env —
 * all in one command.
 *
 * Usage:
 *   php migrations/000_bootstrap.php [root_password] [season_year]
 *
 * Defaults: root password is blank (DBngin's default for a fresh service),
 * season year is 2026. Safe to re-run — every step is idempotent.
 */

$rootPassword = $argv[1] ?? '';
$seasonYear = isset($argv[2]) ? (int) $argv[2] : 2026;

$host = '127.0.0.1';
$port = 3306;
$appDbName = 'pickem';
$appDbUser = 'pickem';

function fail(string $msg): never
{
    fwrite(STDERR, "\n✗ $msg\n\n");
    exit(1);
}

echo "== FRSN Pick'em local bootstrap ==\n\n";

// --- Connect as root, no dbname yet -------------------------------------------------------------
echo "1. Connecting to MySQL at $host:$port as root...\n";
try {
    $root = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", 'root', $rootPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    fail(
        "Couldn't connect as root. Things to check:\n" .
        "   - Is the 'kyldev' service actually started in DBngin? (the Start button was showing red)\n" .
        "   - If root has a password, pass it: php migrations/000_bootstrap.php YOURPASSWORD\n" .
        "   Original error: " . $e->getMessage()
    );
}
echo "   connected.\n\n";

// --- Create database + app user -------------------------------------------------------------
echo "2. Creating database and app user (safe if they already exist)...\n";
$appDbPass = 'pickem_' . bin2hex(random_bytes(6));

// Reuse an existing app-user password from .env if one's already there, so
// re-running this doesn't orphan an existing .env with a stale password.
$envPath = dirname(__DIR__) . '/.env';
$existingPass = null;
if (is_file($envPath)) {
    foreach (file($envPath) as $line) {
        if (str_starts_with(trim($line), 'DB_PASS=')) {
            $existingPass = trim(substr(trim($line), strlen('DB_PASS=')));
        }
    }
}

$root->exec("CREATE DATABASE IF NOT EXISTS `$appDbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

if ($existingPass) {
    // App user probably already exists with this password — make sure it does, and grant.
    $root->exec("CREATE USER IF NOT EXISTS '$appDbUser'@'localhost' IDENTIFIED BY '$existingPass'");
    $appDbPass = $existingPass;
} else {
    $root->exec("DROP USER IF EXISTS '$appDbUser'@'localhost'");
    $root->exec("CREATE USER '$appDbUser'@'localhost' IDENTIFIED BY '$appDbPass'");
}
$root->exec("GRANT ALL PRIVILEGES ON `$appDbName`.* TO '$appDbUser'@'localhost'");
$root->exec('FLUSH PRIVILEGES');
echo "   done.\n\n";

// --- Write .env -------------------------------------------------------------
echo "3. Writing .env...\n";
$envContents = <<<ENV
DB_HOST=$host
DB_NAME=$appDbName
DB_USER=$appDbUser
DB_PASS=$appDbPass
APP_ENV=local
SESSION_COOKIE_DAYS=365
ENV;
file_put_contents($envPath, $envContents . "\n");
echo "   wrote $envPath\n\n";

// --- Load schema -------------------------------------------------------------
echo "4. Loading schema...\n";
$app = new PDO("mysql:host=$host;port=$port;dbname=$appDbName;charset=utf8mb4", $appDbUser, $appDbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$schemaPath = __DIR__ . '/001_initial_schema.sql';
$sql = file_get_contents($schemaPath);
// Strip full-line comments before splitting, so a comment block sitting
// directly above a CREATE TABLE doesn't get glued onto it and filtered out
// whole (an early version of this script did exactly that to the seasons
// table, which then broke every foreign key pointing at it).
$lines = array_filter(explode("\n", $sql), fn($l) => !str_starts_with(ltrim($l), '--'));
$sql = implode("\n", $lines);
$statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => $s !== '');
foreach ($statements as $stmt) {
    $app->exec($stmt);
}
echo "   applied " . count($statements) . " statements from " . basename($schemaPath) . "\n\n";

// --- Seed NFL teams, if the frsn assets folder is where we expect it -------------------------------------------------------------
echo "5. Seeding NFL teams...\n";
$home = getenv('HOME') ?: (getenv('USERPROFILE') ?: '');
$manifestPath = "$home/Sites/frsn/assets/team-logos/manifest.json";
$logoDir = "$home/Sites/frsn/assets/team-logos/pro";
if (is_file($manifestPath) && is_dir($logoDir)) {
    ob_start();
    $_SERVER['argv'] = ['seed_nfl_teams.php', $manifestPath, $logoDir];
    $argv = $_SERVER['argv'];
    include __DIR__ . '/seed_nfl_teams.php';
    $out = ob_get_clean();
    echo "   " . trim($out) . "\n\n";
} else {
    echo "   skipped — didn't find $manifestPath\n";
    echo "   run it yourself once you know the right path:\n";
    echo "   php migrations/seed_nfl_teams.php /path/to/manifest.json /path/to/team-logos/pro\n\n";
}

// --- Active season -------------------------------------------------------------
echo "6. Creating the $seasonYear season (if it doesn't already exist)...\n";
$stmt = $app->prepare('SELECT id FROM seasons WHERE year = :year');
$stmt->execute([':year' => $seasonYear]);
if ($stmt->fetch()) {
    echo "   $seasonYear season already exists — leaving it alone.\n\n";
} else {
    $app->prepare("INSERT INTO seasons (year, status) VALUES (:year, 'active')")
        ->execute([':year' => $seasonYear]);
    echo "   created.\n\n";
}

echo "== Done. ==\n";
echo "Next: park this folder with Valet, paste the location blocks from nginx.conf.example\n";
echo "into the generated config, then visit the site and try /signup.\n";
