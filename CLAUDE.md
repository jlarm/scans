# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Architecture Overview

This is a Laravel Vue Starter Kit application that uses:
- **Backend**: Laravel 12 with PHP 8.2+
- **Frontend**: Vue 3 with TypeScript, Inertia.js for SPA-like experience
- **UI Components**: Custom UI library built with Reka UI (headless components)
- **Styling**: Tailwind CSS v4 with dark/light mode support
- **Testing**: Pest PHP for backend testing
- **Bundling**: Vite with Laravel plugin

The application follows a standard Laravel structure with Vue frontend components located in `resources/js/`. Authentication is built-in with user registration, login, password reset, and settings management.

## Development Commands

### Backend (Laravel/PHP)
- **Start development server**: `composer run dev` (runs Laravel server, queue, logs, and Vite concurrently)
- **SSR development**: `composer run dev:ssr` (includes server-side rendering)
- **Run tests**: `composer run test` or `php artisan test`
- **Code formatting**: `./vendor/bin/pint` (Laravel Pint)
- **Individual services**:
  - Laravel server: `php artisan serve`
  - Queue worker: `php artisan queue:listen --tries=1`
  - Log monitoring: `php artisan pail --timeout=0`

### Frontend (Vue/TypeScript)
- **Build for production**: `npm run build`
- **Build with SSR**: `npm run build:ssr`
- **Development**: `npm run dev` (Vite dev server)
- **Linting**: `npm run lint` (ESLint with auto-fix)
- **Formatting**: `npm run format` (Prettier)
- **Format check**: `npm run format:check`

### Testing
- **Run all tests**: `php artisan test` or `vendor/bin/pest`
- **Run specific test file**: `php artisan test tests/Feature/DashboardTest.php`
- **Test with coverage**: `vendor/bin/pest --coverage`

## Key Architecture Patterns

### Frontend Structure
- **Pages**: Located in `resources/js/pages/` - Inertia.js page components
- **Layouts**: Multi-level layout system in `resources/js/layouts/`
  - `AppLayout.vue` - Main authenticated layout
  - `AuthLayout.vue` - Authentication pages layout
  - Nested layouts for specific sections (app/, auth/, settings/)
- **Components**: Reusable components in `resources/js/components/`
- **UI Components**: Headless UI components in `resources/js/components/ui/`
- **Composables**: Vue composition functions in `resources/js/composables/`

### Backend Structure
- **Controllers**: Organized by feature (Auth/, Settings/)
- **Routes**: Separated into logical files (`web.php`, `auth.php`, `settings.php`)
- **Middleware**: Custom middleware for appearance handling and Inertia requests

### Theme/Appearance System
- Uses `useAppearance` composable for light/dark/system theme management
- Persists theme preference in both localStorage and cookies for SSR
- System theme detection with automatic updates

### Testing Strategy
- Uses Pest PHP testing framework
- Feature tests for authentication flows and main functionality
- Tests located in `tests/Feature/` and `tests/Unit/`
- SQLite in-memory database for testing

## Route Structure
- Root routes in `routes/web.php`
- Authentication routes in `routes/auth.php` 
- Settings routes in `routes/settings.php` (profile, password, appearance)
- All authenticated routes use `auth` middleware
- Settings redirect `/settings` to `/settings/profile`

## Build and Deployment
- Production builds require both `npm run build` and Laravel optimization
- SSR support available with `npm run build:ssr` and `php artisan inertia:start-ssr`
- Uses SQLite database (database.sqlite) for development