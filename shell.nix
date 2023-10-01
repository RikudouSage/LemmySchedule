{ pkgs ? import <nixpkgs> {} }:
pkgs.mkShell {
    nativeBuildInputs = with pkgs.buildPackages;
    let
        php82 = pkgs.php82.buildEnv {
            extensions = ({ enabled, all }: enabled ++ (with all; [
                    xdebug
                    redis
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
        php82
        php82.packages.composer
        php82Extensions.redis
        redis
        symfony-cli
        yarn
    ];
}
