# GIAS — project rules

## Stack
Laravel 13 (PHP 8.5, XAMPP) API in backend/ · PostgreSQL 18 + PostGIS 3.7 (local, 127.0.0.1:5432) · clickbar/laravel-magellan for spatial columns
Vue 3 + TypeScript + Vite + Tailwind v4 + Pinia + Vue Router + Leaflet in frontend/ · Sanctum SPA cookie auth
Run: backend `php artisan serve --host=localhost --port=8000` · frontend `npm run dev` (localhost:5173)

## Backend architecture (mandatory)
- Flow: Route → Controller → Service → Eloquent model. NO repository layer.
- Controllers are thin: accept a Form Request, call ONE service method, return an API Resource. No queries or business logic in controllers.
- All business logic and queries live in services. Multi-table writes use DB::transaction() inside the service.
- Flat folders, no domain subfolders: app/Http/Controllers/XController.php, app/Services/XService.php, app/Http/Requests/StoreXRequest.php, app/Http/Resources/XResource.php.
- Only GeocodingService has an interface (app/Services/IGeocodingService.php), bound in AppServiceProvider. Every other service is a concrete class injected via the constructor.
- Validation only in Form Request classes. JSON output only through API Resources.
- Routes in routes/api.php behind auth:sanctum plus a role middleware (admin, coordinator, partner, student).
- Geocoding: Nominatim (OpenStreetMap). Store coordinates as PostGIS Point, SRID 4326.
- Every new endpoint gets a feature test.

## Roles
Admin (system-wide; full CRUD on Coordinator and Industry Partner accounts) · OJT Coordinator (per program; created by Admin only)
Industry Partner (self-registers, Admin approves) · Student (self-registers, their program's Coordinator approves)

## Frontend
- TypeScript everywhere. One axios instance (src/lib/http.ts). Call the API only through src/api/* modules, never directly from components.
- Pinia store per domain; auth state in stores/auth.ts; route guards check role.
- UI follows the GIAS UI Mockups: brand green #006B2B, gold accent #C9A54E, Source Serif 4 / IBM Plex Sans / IBM Plex Mono.

## Workflow
- One module per task. Don't build features that weren't asked for.
- Don't commit. I commit myself with /commit.
