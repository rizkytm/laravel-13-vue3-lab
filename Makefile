.PHONY: dev be fe queue test lint

# Jalankan semua (Ctrl-C berhenti semua)
dev:
	@trap 'kill 0' EXIT; $(MAKE) be & $(MAKE) fe & $(MAKE) queue & wait

be:
	php artisan serve

fe:
	cd frontend && npm run dev

queue:
	php artisan queue:work

test:
	php artisan test
	cd frontend && npm test

lint:
	php vendor/bin/pint --test
	cd frontend && npm run lint && npm run typecheck
