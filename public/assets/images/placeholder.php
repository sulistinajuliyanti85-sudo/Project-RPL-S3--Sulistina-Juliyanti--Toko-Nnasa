<?php
/**
 * Dynamic Elegant SVG Image Generator
 * Produces aesthetic, luxury jewelry placeholder images with dusty pink & rose gold gradients
 */

$text = $_GET['text'] ?? 'nnasa_ca';
$text = htmlspecialchars(urldecode($text), ENT_QUOTES, 'UTF-8');
$type = $_GET['type'] ?? 'product';

header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=86400');

// Determine dimensions based on type
if ($type === 'banners') {
    $width = 1200;
    $height = 420;
    $fontSize = 32;
    $icon = '<circle cx="600" cy="180" r="50" fill="url(#rgGrad)" opacity="0.15"/><path d="M580,180 L600,160 L620,180 L600,200 Z" fill="#b76e79"/>';
} elseif ($type === 'categories') {
    $width = 400;
    $height = 400;
    $fontSize = 20;
    $icon = '<circle cx="200" cy="170" r="45" fill="url(#rgGrad)" opacity="0.2"/><path d="M185,170 C185,155 215,155 215,170 C215,185 185,185 185,170 Z" fill="none" stroke="#b76e79" stroke-width="3"/>';
} else {
    // Products
    $width = 600;
    $height = 600;
    $fontSize = 24;
    $icon = '<g transform="translate(260, 210) scale(1.6)">
        <circle cx="25" cy="25" r="24" fill="url(#rgGrad)" opacity="0.25"/>
        <path d="M25,8 L29,20 L41,21 L32,30 L35,42 L25,36 L15,42 L18,30 L9,21 L21,20 Z" fill="#b76e79" opacity="0.85"/>
        <circle cx="25" cy="25" r="4" fill="#ffffff"/>
    </g>';
}

echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}">
  <defs>
    <linearGradient id="bgGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#FCF6F5"/>
      <stop offset="50%" stop-color="#F7ECEB"/>
      <stop offset="100%" stop-color="#EED8DA"/>
    </linearGradient>
    <linearGradient id="rgGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#e0a9af"/>
      <stop offset="100%" stop-color="#b76e79"/>
    </linearGradient>
    <filter id="softGlow" x="-20%" y="-20%" width="140%" height="140%">
      <feGaussianBlur stdDeviation="8" result="blur"/>
      <feComposite in="SourceGraphic" in2="blur" operator="over"/>
    </filter>
  </defs>

  <!-- Background -->
  <rect width="{$width}" height="{$height}" fill="url(#bgGrad)"/>
  
  <!-- Subtle decorative ring -->
  <circle cx="{$width}/2" cy="{$height}/2" r="{$height} * 0.38" fill="none" stroke="#E3C3C7" stroke-width="1.5" stroke-dasharray="4,6" opacity="0.5"/>

  <!-- Icon Graphic -->
  {$icon}

  <!-- Typography -->
  <text x="50%" y="{$height} * 0.72" text-anchor="middle" font-family="'Plus Jakarta Sans', 'Segoe UI', sans-serif" font-weight="600" font-size="{$fontSize}" fill="#4d3a40" letter-spacing="0.5">
    {$text}
  </text>
  
  <text x="50%" y="{$height} * 0.82" text-anchor="middle" font-family="'Playfair Display', Georgia, serif" font-style="italic" font-size="14" fill="#b76e79" letter-spacing="2">
    nnasa_ca &bull; ACCESSORIES
  </text>
</svg>
SVG;
