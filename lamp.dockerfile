FROM php:8.3.8-apache-bookworm as lamp_stack

WORKDIR /app

RUN apt-get update && apt-get install -y vim build-essential less lsb-release jq net-tools curl wget
RUN wget https://gist.githubusercontent.com/dberry-sage/6af3672d9fb31f4ec88e075f077d7853/raw/265c6a733aaeef0d1f7b9aee4102808f4b56b5f2/.vimrc -O ~/.vimrc


CMD ["sleep", "infinity"]