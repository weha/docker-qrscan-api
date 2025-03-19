FROM php:8.4.5-apache
COPY src/ /var/www/html/

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY config/qrscan.ini "$PHP_INI_DIR/conf.d/"
COPY script/qrscan_opencv.py /var/www/

# Get Ubuntu packages
RUN apt-get update && apt-get install -y \
    build-essential \
    curl \
    libclang-dev \
    zbar-tools \
    python3-pip \
    libgl1 \
    python3-opencv

#RUN pip3 install opencv-python

# Create home folders
RUN chown www-data:www-data /var/www/qrscan_opencv.py
RUN mkdir /var/www/.cargo && chown www-data:www-data /var/www/.cargo 
RUN mkdir /var/www/.rustup && chown www-data:www-data /var/www/.rustup
RUN touch /var/www/.profile && chown www-data:www-data /var/www/.profile
RUN touch /var/www/.bashrc && chown www-data:www-data /var/www/.bashrc

# Change user to www-data
USER 'www-data'
WORKDIR /var/www

# Get Rust manually & Install QRScan
ENV PATH="/var/www/.cargo/bin:${PATH}"
RUN curl https://sh.rustup.rs -sSf | bash -s -- -y && /var/www/.cargo/bin/cargo install --locked --force qrscan
RUN echo 'source $HOME/.cargo/env' >> $HOME/.bashrc

# su -l www-data -s /bin/bash

#ENV PORT=4567
EXPOSE 80

#CMD [ "php", "./your-script.php" ]