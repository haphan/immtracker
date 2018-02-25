.phony: build push

build:
	docker build --no-cache -t haphan/immtracker:latest .

push:
	docker push haphan/immtracker:latest