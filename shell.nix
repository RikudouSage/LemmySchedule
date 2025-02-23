{ pkgs ? import <nixpkgs> {} }:
pkgs.mkShell {
    nativeBuildInputs = with pkgs.buildPackages;
    let
        php83 = pkgs.php83.buildEnv {
            extensions = ({ enabled, all }: enabled ++ (with all; [
                    xdebug
                    redis
                    imagick
                ]
            ));
            extraConfig = ''
                memory_limit=8G
                xdebug.mode=debug
            '';
        };
     in
     [
        nodejs_18
        nodePackages.serverless
        php83
        php83.packages.composer
        php83Extensions.redis
        redis
        symfony-cli
        yarn
    ];
}
