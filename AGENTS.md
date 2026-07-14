# Development Guidelines

## General Principles

* Act as a Senior Laravel Developer.
* Prioritize simplicity, readability, maintainability, and testability.
* Favor incremental changes over large refactorings.
* Avoid over-engineering and speculative abstractions.
* Minimize the number of files and overall complexity whenever possible.
* Before introducing a new pattern or abstraction layer, justify why it is necessary.

## Laravel Conventions

* Prefer Laravel conventions over custom architectures.
* Follow Laravel naming conventions and project structure.
* Use dependency injection where appropriate.
* Prefer framework features before building custom solutions.
* Use Form Requests for validation in HTTP endpoints when appropriate.

## Architecture

* Reuse existing services and patterns before creating new ones.
* Do not introduce repositories unless the project already uses them or there is a clear need.
* Do not introduce interfaces for a single implementation.
* Keep controllers and handlers as simple as possible.
* Place business rules outside controllers when complexity justifies it.
* Respect the existing architecture and coding style of the project.

## Code Quality

* Follow Clean Code principles.
* Use descriptive, intention-revealing names.
* Keep methods and classes focused on a single responsibility.
* Prefer explicit, easy-to-understand code over clever but difficult-to-maintain solutions.
* Remove dead, duplicated, or unused code.

## Testing

* Add or update tests for every behavior change.
* Do not consider a task complete until all relevant tests pass.
* Bug fixes should include regression tests whenever appropriate.
* Reuse the existing testing patterns and conventions already present in the project.

## Pull Requests

Before considering a task complete:

1. Run the relevant test suite.
2. Verify code formatting and style.
3. Review the impact of the changes made.
4. Summarize the technical or architectural decisions taken.
5. Identify risks, limitations, and potential future improvements.

## What to Avoid

* Do not introduce unnecessary abstraction layers.
* Do not create complex patterns to solve simple problems.
* Do not rewrite existing code without a clear reason.
* Do not make large-scale changes when a small change solves the problem.
* Do not introduce external dependencies without justifying their value.

## Development Environment

### Docker

This project runs entirely inside Docker.

Do **not** execute Laravel or Composer commands directly on the host machine.

Always execute commands inside the `laravel-app` container.

### Laravel Commands

Use the following pattern for any Artisan command:

```bash
docker exec -it laravel-app php artisan <command>
```

Examples:

```bash
docker exec -it laravel-app php artisan test
docker exec -it laravel-app php artisan migrate
docker exec -it laravel-app php artisan optimize:clear
docker exec -it laravel-app php artisan tinker
```

### Composer

Run Composer commands inside the container:

```bash
docker exec -it laravel-app composer install
docker exec -it laravel-app composer dump-autoload
```

### Testing

Always run the test suite using:

```bash
docker exec -it laravel-app php artisan test
```

Never run `php artisan` or `composer` commands directly from the host environment.


## Task Management

When working on a feature or bug fix:

* If a Linear issue already exists, update it with a brief implementation summary.
* If no issue exists, create a new one before considering the task complete.
* Keep the summary concise and focused on the implemented behavior.
* Include an estimated implementation time based on the actual work performed.
* Do not close the issue automatically unless explicitly instructed.

The summary should include:

* What was implemented.
* Any important technical decisions.
* Estimated time spent.
