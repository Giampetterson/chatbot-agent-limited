<?php
/**
 * Security Headers for Safari iOS Microphone Access
 * Created: 2025-09-03 - Milestone 1
 * Include this file in pages that need microphone access
 */

// Only set headers if not already sent
if (!headers_sent()) {
    // CRITICO: Permissions Policy per Safari iOS
    header('Permissions-Policy: microphone=(self), camera=(self), geolocation=(self), notifications=(self)');
    
    // Content Security Policy with media-src
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.gstatic.com; font-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com; media-src 'self' blob: data:; connect-src 'self' https://s5pdsmwvr6vyuj6gwtaedp7g.agents.do-ai.run https://api.openai.com; img-src 'self' data: https:;");
    
    // Additional security headers
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    
    // Force HTTPS
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
        $redirectURL = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header("Location: $redirectURL", true, 301);
        exit();
    }
}
?>