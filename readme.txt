# Yiroinc Core Backend

Yiroinc Core Backend is the custom WordPress plugin that powers the backend functionality and REST API for the Yiroinc Academia platform.

The plugin provides the core application logic used by the frontend, including authentication, user management, orders, payments, file uploads, products, invitations, and other platform services.

## Architecture

The platform uses a headless WordPress architecture:

- **Backend:** WordPress + Yiroinc Core custom plugin
- **API:** WordPress REST API
- **Frontend:** React
- **Frontend Hosting:** Cloudflare Pages

The frontend communicates with this plugin through REST API endpoints.

## Development

Backend development is done locally and tracked using Git.

Changes should be tested before being deployed to the production WordPress installation.

## Repository Structure

- `yiroinc-core.php` - Main plugin file
- `includes/` - Core plugin classes, controllers, services, and functionality
- `uninstall.php` - Plugin uninstall handling
- `readme.txt` - WordPress plugin information

## Status

Currently under active development.

## Maintained by

Yiroinc
