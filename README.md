# utils

## Install and configuration steps

### Use composer

```bash
composer require "condoedge/utils"
```

### Migrate database

```bash
php artisan migrate
```

### Add HasUserSettings trait

In your User model, add the `HasUserSettings` trait:

```php

use CondoEdge\Utils\Traits\HasUserSettings;

class User extends Authenticatable
{
    use HasUserSettings;

    // Your user model code...
}
```

### Publish config file

```bash
php artisan vendor:publish --provider="CondoEdge\Utils\UtilsServiceProvider" --tag=kompo-utils-config
```

### Publish icons

```bash
php artisan vendor:publish --provider="CondoEdge\Utils\UtilsServiceProvider" --tag=kompo-kompo-utils-icons
```

### Publish scripts

```bash
php artisan vendor:publish --provider="CondoEdge\Utils\UtilsServiceProvider" --tag=kompo-utils-assets
```

### Load styles

```scss
// In you main scss file
@use "../../vendor/condoedge/utils/resources/sass/kompo-utils";
```

### Load scripts

```javascript
// In your main js file
import utils from '../../vendor/condoedge/utils/resources/js/utils';
window.utils = utils;
```

## IntroJS Animations

More explanations soon. For now just a minor clarification to set the image tooltip for IntroJS animations.

```scss
$introjs-image-tooltip: url('../images/introjs/introjs-image-tooltip.png');
```