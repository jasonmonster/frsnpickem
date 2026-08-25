<?php
/**
 * One-time seed: loads the 362 D1 college teams from the FRSN team-logos
 * manifest into college_teams, and copies their logo files into
 * assets/team-logos (same flat directory the NFL logos live in — filenames
 * are already prefixed `ncaa-` vs `nfl-`, so there's no collision).
 *
 * Run migrations/008_add_college_teams.php first if the table doesn't exist
 * yet. Safe to re-run — upserts by espn_id, and copy() overwrites in place.
 *
 * Usage: php seed_college_teams.php /path/to/manifest.json /path/to/team-logos/college
 */

require __DIR__ . '/../src/Database.php';

$manifestPath = $argv[1] ?? null;
$logoSourceDir = $argv[2] ?? null;

if (!$manifestPath || !$logoSourceDir) {
    fwrite(STDERR, "Usage: php seed_college_teams.php <manifest.json> <team-logos/college dir>\n");
    exit(1);
}

$manifest = json_decode(file_get_contents($manifestPath), true);
if (!$manifest || !isset($manifest['teams'])) {
    fwrite(STDERR, "Could not parse manifest at $manifestPath\n");
    exit(1);
}

$collegeTeams = array_values(array_filter($manifest['teams'], fn($t) => ($t['lg'] ?? '') === 'ncaa'));
if (count($collegeTeams) === 0) {
    fwrite(STDERR, "No 'ncaa' entries found in the manifest — check the path.\n");
    exit(1);
}

$logoDestDir = __DIR__ . '/../assets/team-logos';
if (!is_dir($logoDestDir)) {
    mkdir($logoDestDir, 0755, true);
}

$pdo = Pickem\Database::connect();

$stmt = $pdo->prepare(
    'INSERT INTO college_teams
        (espn_id, slug, name, short_name, abbr, color_primary, color_secondary, on_navy, logo_std, logo_dark, logo_white)
     VALUES (:espn_id, :slug, :name, :short_name, :abbr, :color_primary, :color_secondary, :on_navy, :logo_std, :logo_dark, :logo_white)
     ON DUPLICATE KEY UPDATE
        slug = VALUES(slug), name = VALUES(name), short_name = VALUES(short_name), abbr = VALUES(abbr),
        color_primary = VALUES(color_primary), color_secondary = VALUES(color_secondary),
        on_navy = VALUES(on_navy), logo_std = VALUES(logo_std), logo_dark = VALUES(logo_dark), logo_white = VALUES(logo_white)'
);

$copied = 0;
$missing = 0;
foreach ($collegeTeams as $t) {
    $stmt->execute([
        ':espn_id'         => (int) $t['id'],
        ':slug'            => $t['slug'],
        ':name'            => $t['name'],
        ':short_name'      => $t['short'],
        ':abbr'            => $t['abbr'],
        ':color_primary'   => $t['c1'] ?: '#1C305E',
        ':color_secondary' => $t['c2'] ?: '#FFFFFF',
        ':on_navy'         => $t['on_navy'] ?: 'std',
        ':logo_std'        => $t['std'],
        ':logo_dark'       => $t['dark'],
        ':logo_white'      => $t['white'],
    ]);

    foreach (['std', 'dark', 'white'] as $cut) {
        $file = $t[$cut] ?? null;
        if (!$file) {
            continue; // a handful of schools are missing one cut — see frsn-college-pro-logo-assets.md
        }
        $src = rtrim($logoSourceDir, '/') . '/' . $file;
        $dst = $logoDestDir . '/' . $file;
        if (is_file($src)) {
            copy($src, $dst);
            $copied++;
        } else {
            $missing++;
            fwrite(STDERR, "Missing logo file: $src\n");
        }
    }
}

echo "Seeded " . count($collegeTeams) . " college teams, copied $copied logo files to $logoDestDir" . ($missing ? " ($missing missing)" : '') . "\n";
