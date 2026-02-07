cli:
	docker exec -it mail-import-php bash
down:
	docker-compose down
up:
	docker-compose up -d
node:
	docker exec -it mail-import-node bash
dev: 
	docker exec  mail-import-node npm run dev
build: s
	docker exec  mail-import-node npm run build