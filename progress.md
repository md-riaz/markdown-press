# Progress Tracking

- [x] Explore implementation gaps against `docs/roadmap.md` using parallel sub-agents
- [x] Complete missing Phase 2 shortcode handlers (`gallery`, `cta`, `toc`, `code`, `notice`, `columns`, `post_list`)
- [x] Ensure shortcode handler registration in `config/shortcodes.php`
- [x] Add and stabilize quality tests for content and API flows
- [x] Fix API post CRUD feature tests to use correct token headers
- [x] Return HTTP 201 for successful post creation API endpoint
- [x] Validate with `php artisan migrate:fresh --seed --force`
- [x] Validate with `php artisan test`
- [x] Implement `GET /api/v1/auth/tokens` for authenticated token listing
- [x] Implement `GET /api/v1/media/{id}` for public media metadata
- [x] Add feature coverage for API token listing and media metadata endpoints
- [x] Implement newsletter service plus `POST /api/v1/newsletter/subscribe` and `DELETE /api/v1/newsletter/unsubscribe`
- [x] Complete `SubscriberResource` CSV export and unsubscribe actions
- [x] Apply docs-based API rate limiting for public, authenticated, and auth token routes
- [x] Add feature coverage for API and auth rate limiting responses
- [x] Run the project locally in the container and capture preview screenshots for seeded public and admin pages
- [x] Add a README preview section linking the captured screenshots
- [ ] Continue next roadmap slice (security hardening headers and remaining admin completion)
