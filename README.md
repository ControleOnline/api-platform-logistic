# quote


`composer require controleonline/quote:dev-master`



Create a new fila on controllers:
config\routes\controllers\quote.yaml

```yaml
controllers:
    resource: ../../vendor/controleonline/quote/src/Controller/
    type: annotation      
```

Add to entities:
nelsys-api\config\packages\doctrine.yaml
```yaml
doctrine:
    orm:
        mappings:
           quote:
                is_bundle: false
                type: annotation
                dir: "%kernel.project_dir%/vendor/controleonline/quote/src/Entity"
                prefix: 'ControleOnline\Entity'
                alias: ControleOnline                             
```          


Add this line on your routes:
config\packages\api_platform.yaml
```yaml          
mapping   :
    paths: ['%kernel.project_dir%/src/Entity','%kernel.project_dir%/src/Resource',"%kernel.project_dir%/vendor/controleonline/quote/src/Entity"]        
```          
