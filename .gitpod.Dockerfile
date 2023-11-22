FROM gitpod/workspace-full:2022-05-08-14-31-53

RUN sudo update-alternatives --set php $(which php7.4)

RUN sudo install-packages php-xdebug
