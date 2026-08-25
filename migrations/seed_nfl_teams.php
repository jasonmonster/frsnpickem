<?php
/**
 * One-time seed: loads the 32 NFL teams from the FRSN team-logos manifest
 * into nfl_teams, and copies their logo files into assets/team-logos.
 *
 * Usage: php seed_nfl_teams.php /path/to/manifest.json /path/to/team-logos/pro /path/to/pdo-dsn-env
 */

require __DIR__ . '/../src/Database.php';

$manifestPath = $argv[1] ?? null;
$logoSourceDir = $argv[2] ?? null;

if (!$manifestPath || !$logoSourceDir) {
    fwrite(STDERR, "Usage: php seed_nfl_teams.php <manifest.json> <team-logos/pro dir>\n");
    exit(1);
}

$manifest = json_decode(file_get_contents($manifestPath), true);
if (!$manifest || !isset($manifest['teams'])) {
    fwrite(STDERR, "Could not parse manifest at $manifestPath\n");
    exit(1);
}

$nflTeams = array_values(array_filter($manifest['teams'], fn($t) => ($t['lg'] ?? '') === 'nfl'));
if (count($nflTeams) !== 32) {
    fwrite(STDERR, "Expected 32 NFL teams, found " . count($nflTeams) . " — check the manifest.\n");
}

$logoDestDir = __DIR__ . '/../assets/team-logos';
if (!is_dir($logoDestDir)) {
    mkdir($logoDestDir, 0755, true);
}

$pdo = Pickem\Database::connect();

$stmt = $pdo->prepare(
    'INSERT INTO nfl_teams
        (espn_id, slug, name, short_name, abbr, color_primary, color_secondary, on_navy, logo_std, logo_dark, logo_white)
     VALUES (:espn_id, :slug, :name, :short_name, :abbr, :color_primary, :color_secondary, :on_navy, :logo_std, :logo_dark, :logo_white)
     ON DUPLICATE KEY UPDATE
        slug = VALUES(slug), name = VALUES(name), short_name = VALUES(short_name), abbr = VALUES(abbr),
        color_primary = VALUES(color_primary), color_secondary = VALUES(color_secondary),
        on_navy = VALUES(on_navy), logo_std = VALUES(logo_std), logo_dark = VALUES(logo_dark), logo_white = VALUES(logo_white)'
);

$copied = 0;
foreach ($nflTeams as $t) {
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
        $file = $t[$cut];
        $src = rtrim($logoSourceDir, '/') . '/' . $file;
        $dst = $logoDestDir . '/' . $file;
        if (is_file($src)) {
            copy($src, $dst);
            $copied++;
        } else {
            fwrite(STDERR, "Missing logo file: $src\n");
        }
    }
}

echo "Seeded " . count($nflTeams) . " NFL teams, copied $copied logo files to $logoDestDir\n";
