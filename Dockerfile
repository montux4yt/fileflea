FROM php:8.1

WORKDIR /app

COPY . .

CMD [ "php", "-S", "0.0.0.0:8000" ] 
