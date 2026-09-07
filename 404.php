<?php
declare(strict_types=1);
if (http_response_code() < 400) http_response_code(404);
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Page not found | CloudSys</title><meta name="robots" content="noindex,nofollow"><link rel="icon" href="/assets/cloudsys-logo.png"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&amp;family=Manrope:wght@400;500;600;700;800&amp;family=Newsreader:ital,wght@1,500&amp;display=swap" rel="stylesheet"><link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/accessibility.css"><link rel="stylesheet" href="/error-pages.css"></head><body class="error-page"><main><a class="brand" href="/"><img src="/assets/cloudsys-logo.png" width="794" height="243" alt="CloudSys"></a><p class="error-code">404 · PAGE NOT FOUND</p><h1>This page isn’t<br>part of the <em>workflow.</em></h1><p>The address may have changed, or the page is no longer publicly available.</p><div><a class="error-primary" href="/">Return home <span>→</span></a><a href="/#contact">Talk to an expert</a></div></main></body></html>
