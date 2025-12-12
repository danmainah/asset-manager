@echo off

echo Stopping and removing containers...
docker-compose down

echo Building and starting containers...
docker-compose up -d --build

echo Waiting for database to be ready...
timeout /t 30

echo Running migrations...
docker-compose exec app php artisan migrate --force

echo Clearing cache...
docker-compose exec app php artisan cache:clear

echo Asset Manager is ready!
echo Backend: http://localhost:8000
echo Frontend: http://localhost:3000
