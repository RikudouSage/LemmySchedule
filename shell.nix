{ pkgs ? import <nixpkgs> {} }:
pkgs.mkShell {
    nativeBuildInputs = with pkgs.buildPackages;
    let
        php84 = pkgs.php84.buildEnv {
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
        php84
        php84.packages.composer
        php84Extensions.redis
        redis
        symfony-cli
        yarn
    ];
}
