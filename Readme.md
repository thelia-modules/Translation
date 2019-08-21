# Translation

This module allow you to export and import translations in ```.po``` or ```.xlf```. 

## Installation

### Manually

* Copy the module into ```<thelia_root>/local/modules/``` directory and be sure that the name of the module is Translation.
* Activate it in your thelia administration panel

### Composer

Add it in your main thelia composer.json file

```
composer require thelia/translation-module ~1.0.0
```

## Usage

This module adds a new page in back office called ```Translations```, on this new page you can select the format of your translations (```.po``` or ```.xlf```), then you can select which part of your translations you want to export or you can import the translations that you have modified.

**For the import to work, you must send a zip file, and it is very important than you respect the folder architecture of the export like :**

```
po
├── backOffice
│   └── bo.default
│       ├── messages.fr.po
│       ├── message.en.po
├── modules
│   ├── <module name>
│   │   ├── <domain name>
│   │   │   ├── messages.fr.po
│   │   │   ├── messages.en.po

... etc.
```
