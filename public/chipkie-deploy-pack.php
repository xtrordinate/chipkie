<?php
/**
 * Chipkie Deploy Pack — download all deployment files as a zip
 * https://chipkie-production.up.railway.app/chipkie-deploy-pack.php
 */

$phpFile = __DIR__ . '/loan-apply-beta-9x4k2m.php';

$envContent = <<<'ENV'
# ============================================================
# CHIPKIE AI LOAN CHAT — ENVIRONMENT VARIABLES
# Add these lines to the bottom of my.chipkie.com/.env
# ============================================================

# Anthropic API key — powers the AI loan chat conversation
# Get your key from: https://console.anthropic.com/
ANTHROPIC_API_KEY=sk-ant-YOUR-KEY-HERE
ENV;

$guideContent = <<<'GUIDE'
====================================================================
  CHIPKIE AI LOAN CHAT — DEPLOYMENT GUIDE
  Hidden URL beta release for my.chipkie.com
====================================================================

ESTIMATED EFFORT: 20-30 minutes

--------------------------------------------------------------------
WHAT THIS DOES
--------------------------------------------------------------------
Adds a hidden AI-powered loan creation page to my.chipkie.com.
Claude (AI) guides the user through a natural conversation to
collect all loan details, then creates a real loan in the existing
database — same as the current flow, just AI-driven.

No existing pages or flows are changed.

--------------------------------------------------------------------
FILES IN THIS PACKAGE
--------------------------------------------------------------------
  loan-apply-beta-9x4k2m.php   → upload to my.chipkie.com/public/
  chipkie-variables.env         → values to add to my.chipkie.com/.env
  CHIPKIE-CHAT-DEPLOY.txt       → this file

--------------------------------------------------------------------
STEP 1 — ADD ENVIRONMENT VARIABLE  (~5 mins)
--------------------------------------------------------------------
Open the .env file in the root of the my.chipkie.com Laravel app
(one level above the public/ folder) and add the contents of
chipkie-variables.env to the bottom.

--------------------------------------------------------------------
STEP 2 — UPLOAD THE PHP FILE  (~5 mins)
--------------------------------------------------------------------
Upload loan-apply-beta-9x4k2m.php into the public/ directory.

  public/
  ├── index.php
  ├── loan-apply-beta-9x4k2m.php   ← here
  └── ...

--------------------------------------------------------------------
STEP 3 — CLEAR CONFIG CACHE  (~2 mins)
--------------------------------------------------------------------
Run via SSH or Plesk terminal:

  php artisan config:clear
  php artisan cache:clear

--------------------------------------------------------------------
STEP 4 — TEST  (~10 mins)
--------------------------------------------------------------------
Visit: https://my.chipkie.com/loan-apply-beta-9x4k2m.php

Run a test loan all the way through and confirm it appears in
the database/dashboard.

--------------------------------------------------------------------
REQUIREMENTS CHECK
--------------------------------------------------------------------
  ✓ PHP 8.1+              (already required by Laravel)
  ✓ PHP curl extension    (check: php -m | grep curl)
  ✓ Laravel app working   (yes — my.chipkie.com)
  ✓ ANTHROPIC_API_KEY     (added in Step 1)

--------------------------------------------------------------------
NO DATABASE CHANGES REQUIRED
--------------------------------------------------------------------
Uses existing loans, users, instalments and loan_tokens tables.
Nothing new to create or migrate.

--------------------------------------------------------------------
MAKING IT THE MAIN FLOW (when ready)
--------------------------------------------------------------------
Full Laravel integration is in the repo (branch: main) at:
  https://github.com/xtrordinate/chipkie

Changes needed for full integration:
  app/Http/Controllers/LoanChatAIController.php  (new)
  routes/web.php                                  (updated)
  config/services.php                             (updated)
  resources/js/Pages/LoanChat.vue                 (updated — needs npm run build)

Estimated effort: 1-2 hours including testing.
====================================================================
GUIDE;

$tmp = tempnam(sys_get_temp_dir(), 'chipkie_');
$zip = new ZipArchive();
$zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFile($phpFile, 'loan-apply-beta-9x4k2m.php');
$zip->addFromString('chipkie-variables.env', $envContent);
$zip->addFromString('CHIPKIE-CHAT-DEPLOY.txt', $guideContent);
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="chipkie-deploy-pack.zip"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
unlink($tmp);
