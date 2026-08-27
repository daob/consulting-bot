# Everything you do to this project, in one place. `make` on its own lists it.
#
# The loop is: make check  ->  make serve  ->  make sim  ->  make deploy

SHELL := /bin/bash
PHP   ?= php
PORT  ?= 8000

# Server coordinates live in deploy.env, which is git-ignored.
-include deploy.env

.DEFAULT_GOAL := help
.PHONY: help test lint check serve sim shots deploy-dry deploy remote-check remote-test remote-sim remote-fetch clean

help:  ## show this list
	@echo "consulting bot"
	@echo
	@grep -E '^[a-z-]+:.*?## .*$$' $(MAKEFILE_LIST) \
	  | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[1m%-14s\033[0m %s\n", $$1, $$2}'
	@echo
	@echo "  deploy targets need deploy.env — copy deploy.env.example and fill it in."

## ---------------------------------------------------------------- developing

test:  ## run the unit tests (offline, no API calls, ~2s)
	@command -v $(PHP) > /dev/null || { \
	  echo "php not found locally. Deploy and run 'make remote-test' instead."; exit 1; }
	@$(PHP) tests/run.php

lint:  ## syntax-check every php and js file
	@command -v $(PHP) > /dev/null || { \
	  echo "php not found locally. Deploy and run 'make remote-test' instead."; exit 1; }
	@fail=0; \
	for f in $$(find public private tests tools -name '*.php'); do \
	  $(PHP) -l $$f > /dev/null || fail=1; \
	done; \
	if command -v node > /dev/null; then \
	  for f in $$(find public -name '*.js'); do node --check $$f || fail=1; done; \
	fi; \
	[ $$fail -eq 0 ] && echo "lint: clean" || (echo "lint: FAILED"; exit 1)

check: lint test  ## lint then test — run this before every commit

serve:  ## run the app locally (PORT=8000 by default)
	@echo "http://127.0.0.1:$(PORT)/  (class code from private/config.php)"
	@$(PHP) -S 127.0.0.1:$(PORT) -t public

sim:  ## acceptance harness: simulated students vs the real client (~$0.03)
	@$(PHP) tools/simulate.php --turns=$${TURNS:-8} --styles=$${STYLES:-good,jargon,hostile}

shots:  ## screenshot the three screens in a real browser (needs node + playwright)
	@CLASS_CODE=$${CLASS_CODE:-dev} node tools/screenshots.js http://127.0.0.1:$(PORT)

## ---------------------------------------------------------------- deploying

RSYNC_OPTS = -av --human-readable --chmod=D750,F640
# config.php, students.txt and data/ belong to the server. Never push over them.
PRIVATE_EXCLUDES = --exclude 'config.php' --exclude 'students.txt' --exclude 'data/'

deploy-dry:  ## show exactly what a deploy would change, without changing it
	@$(MAKE) --no-print-directory _rsync EXTRA=--dry-run

deploy: check  ## upload after the tests pass
	@$(MAKE) --no-print-directory _rsync EXTRA=
	@echo
	@echo "Deployed. If anything looks wrong: make remote-check"

_rsync:
	@test -n "$(SSH_TARGET)" || { echo "No deploy.env — copy deploy.env.example."; exit 1; }
	@echo "==> public/  ->  $(REMOTE_PUBLIC)"
	@rsync $(RSYNC_OPTS) $(EXTRA) --delete public/ "$(SSH_TARGET):$(REMOTE_PUBLIC)/"
	@echo "==> private/ ->  $(REMOTE_PRIVATE)   (config, class list and data left alone)"
	@rsync $(RSYNC_OPTS) $(EXTRA) $(PRIVATE_EXCLUDES) private/ "$(SSH_TARGET):$(REMOTE_PRIVATE)/"
	@echo "==> tools/   ->  $(REMOTE_PRIVATE)/tools"
	@rsync $(RSYNC_OPTS) $(EXTRA) --exclude 'check-install.php' tools/ "$(SSH_TARGET):$(REMOTE_PRIVATE)/tools/"
	@echo "==> tests/   ->  $(REMOTE_PRIVATE)/tests"
	@rsync $(RSYNC_OPTS) $(EXTRA) --delete tests/ "$(SSH_TARGET):$(REMOTE_PRIVATE)/tests/"

remote-check:  ## put check-install.php on the server, print the URL, then remove it
	@test -n "$(SSH_TARGET)" || { echo "No deploy.env."; exit 1; }
	@scp -q tools/check-install.php "$(SSH_TARGET):$(REMOTE_PUBLIC)/"
	@echo "Open  <your site>/consult/check-install.php  then press Enter to delete it."
	@read -r _
	@ssh "$(SSH_TARGET)" "rm -f '$(REMOTE_PUBLIC)/check-install.php'"
	@echo "removed."

remote-test:  ## run the test suite on the server (for when php is not installed here)
	@test -n "$(SSH_TARGET)" || { echo "No deploy.env."; exit 1; }
	@ssh "$(SSH_TARGET)" "cd '$(REMOTE_PRIVATE)' && php tests/run.php"

remote-sim:  ## run the acceptance harness on the server against the live config
	@test -n "$(SSH_TARGET)" || { echo "No deploy.env."; exit 1; }
	@ssh "$(SSH_TARGET)" "cd '$(REMOTE_PRIVATE)' && php tools/simulate.php --turns=$${TURNS:-8}"

remote-fetch:  ## copy the transcripts down into transcripts/
	@test -n "$(SSH_TARGET)" || { echo "No deploy.env."; exit 1; }
	@mkdir -p transcripts
	@rsync -av "$(SSH_TARGET):$(REMOTE_PRIVATE)/data/transcripts/" transcripts/
	@echo "$$(ls transcripts | wc -l) transcripts in ./transcripts (git-ignored)"

clean:  ## delete local sessions and transcripts
	@rm -rf private/data
	@echo "local data cleared"
