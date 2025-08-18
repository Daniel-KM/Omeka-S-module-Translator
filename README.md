Translator (module for Omeka S)
===============================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Translator] is a module for [Omeka S] that allows to manage translations of
resource values and site page block strings and to display them in public pages
according to the site locale. Translations can be generated automatically via
[DeepL], a German specialist of quality translations and GPDR compliant.

Translations can be stored directly as value in resources for specific
properties, or stored in a decontextualized way in the database, so translation
can be used anywhere. Multiple translations can be made for each source string.

Some features are not yet available: translation of site page blocks and storage
of translated values in resource itself. See todo below.

See the list of [supported languages by DeepL].


Installation
------------

See general end user documentation for [installing a module].

The module [Common] must be installed first.

The module uses an external library, [deepl-php], so use the release zip to
install it, or use and init the source.

* From the zip

Download the last release [Translator.zip] from the list of releases (the master
does not contain the dependency), and uncompress it in the `modules` directory.

* From the source and for development:

If the module was installed from the source, rename the name of the folder of
the module to `Translator`, and go to the root module, and run:

```sh
composer install --no-dev
```

Then install it like any other Omeka module.

Note: For technical reasons, the module cannot be named "Translate", so
"Translator" is used instead.


Usage
-----

For automatic translation, an api key is needed, so you need to open an account
at [DeepL]. The free account allows to translate 500000 characters by month,
that is large enough in most common cases.


TODO
----

- [x] Divide the sql table internally in two tables, one for strings and one for translations.
- [ ] Check normalization of json-ld.
- [ ] Finalize manual translation; use a tab in resource and a specific menu (see AiGenerator).
- [ ] Store of translated values in resource itself.
- [ ] Use resource template to define properties to translate instead of the main settings.
- [ ] Translation of site page blocks.
- [ ] Add options of DeepL Api.
- [ ] Translate html and xml and manage their options.
- [ ] Add template form to store values or not.
- [ ] Add to api. See https://www.w3.org/TR/json-ld/#language-indexing
- [ ] See https://packagist.org/packages/boxblinkracer/phpunuhi, a framework to validate and manage translations.
- [ ] Allow to get translation in translation/lang to get the translated/lang,
      so avoid some translations pairs. But more complex to get the translation.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.


Copyright
---------

* Copyright Daniel Berthereau, 2025 (see [Daniel-KM] on GitLab)

Initially created for the digital library, the [Curiothèque] of the [Musée Curie].


[Translator]: https://gitlab.com/Daniel-KM/Omeka-S-module-Translator
[Omeka S]: https://omeka.org/s
[DeepL]: https://www.deepl.com
[supported languages by DeepL]: https://developers.deepl.com/docs/getting-started/supported-languages
[Common]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[installing a module]: https://omeka.org/s/docs/user-manual/modules
[deepl-php]: https://packagist.org/packages/deeplcom/deepl-php
[Translator.zip]: https://github.com/Daniel-KM/Omeka-S-module-Translator/releases
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Translator/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Curiothèque]: https://curiotheque.musee.curie.fr/
[Musée curie]: https://musee.curie.fr
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
