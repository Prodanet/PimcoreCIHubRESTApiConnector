# Development

# How to install a bundle inside Pimcore app.

## SimpleRESTAdapterBundle & PimcoreCIHubAdapterBundle
Choose your directories and use them in the following examples. 

Clone repository (fork) to the `/var/SimpleRESTAdapterBundle` directory.

    git clone https://github.com/BrandOriented/SimpleRESTAdapterBundle .

Clone repository (fork) to the `/var/PimcoreCIHubAdapterBundle` directory.

    git clone https://github.com/BrandOriented/PimcoreCIHubAdapterBundle.git .

Add the following as the first elements in the `repositories` section of the `composer.json` file.
```
    {
      "type": "path",
      "url": "./var/SimpleRESTAdapterBundle",
      "options": {
        "symlink": true
      }
    },
    {
      "type": "path",
      "url": "./var/PimcoreCIHubAdapterBundle",
      "options": {
        "symlink": true
      }
    },
```

Replace installation with the local version.

    composer req bo-hub/ci-hub-adapter-bundle "dev-main"
    composer req "bo-hub/ci-hub-api-bundle:dev-main as 3.0"
