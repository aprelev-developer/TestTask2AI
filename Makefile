SHELL := /bin/sh

.PHONY: up down restart build migrate fresh test logs sh db-sh pint stan

## All backend targets — see backend/Makefile for what each one does.
## Run from the project root so nobody needs to `cd backend` first.
up down restart build migrate fresh test logs sh db-sh pint stan:
	$(MAKE) -C backend $@
