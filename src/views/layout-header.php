<?php
/** @var string|null $pageTitle */
use Pickem\Auth;
use Pickem\View;

$me = Auth::currentParticipant();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= View::e($pageTitle ?? "FRSN Pick'em") ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<header class="site">
  <a class="brand" href="/">FRSN Pick'em</a>
  <nav>
    <?php if ($me): ?>
      <a href="/profile"><?= View::e($me['username']) ?></a>
      <a href="/logout">Log out</a>
    <?php else: ?>
      <a href="/login">Log in</a>
      <a href="/signup">Sign up</a>
    <?php endif; ?>
  </nav>
</header>
<main>
