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

Some features are not yet available: storage of translated values in resource
itself. See todo below.

See the list of [supported languages by DeepL].



<a href="https://ateeducacion.github.io/omeka-s-playground/?blueprint=https%3A%2F%2Fgitlab.com%2FDaniel-KM%2FOmeka-S-module-Translator%2F-%2Fraw%2Fmaster%2Fblueprint.json">
    This module can be tested directly in your browser<br/>
    <img src="https://raw.githubusercontent.com/ateeducacion/omeka-s-playground/main/ogimage.png" alt="Try Translator in your browser" width="110">
</a><br>


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

Specify the list of target languages in the main settings. There are two way to
indicate a target language, with or without the source language. if the source
language is not set, it will set as the language set in the previous setting,
else the translator service will do an autodetection, that is generally not
recommended with short strings.

### Site pages of a multilingual site group

A multilingual site is built with the module [Internationalisation]: a group
gathers one site by language, and the pages of the main site are copied into the
other sites of the group, that keep the relations between the copies.

So a copied page contains the same blocks as the original one, and this module
translates them in place: the strings are stored in the page itself, so the site
displays them without any specific process, and a user can correct them
directly.

The direction of the translation is the one of the pairs of languages set above:
for a pair `fr = en-gb`, the pages of the sites of a group whose locale is
French are translated into the pages of the sites of the same group whose locale
is British English. **Only the pairs with an explicit source language are used**:
without it, the original site of a group would be unknown.

A page duplicated as a mirror page is never translated: it displays the original
page, so it has no string of its own.

The data of a block is a free json, so there is no way to know which keys
contain a text for the end user and which ones contain a setting, an id, a
template name or a query. So the keys to translate are listed in the main
settings, one by line, for example "html" or "heading". They are searched
recursively, so the attachments and the grouped blocks are managed. The special
key "page_title" translates the title of the pages themselves. The blocks of
some layouts can be skipped with the second setting.

The blocks of a copy are rebuilt from the blocks of the original page, matched
by position, so they follow its structure, and only the configured keys are
translated. The strings that contain some html are sent to the translation
service with the option of tag handling, so the tags are kept.

To avoid to translate the same block again and again, and to avoid to overwrite
the corrections made by a user, a hash of the original content is stored for
each copy in the table `translate_page`: a block is processed only when the
original text changed. So the job can be run as often as needed.

The keys of the blocks to translate are set with a default list when the module
is installed or upgraded, so the pages are managed without any configuration.
Check them in the main settings: without them, nothing is translated.

#### Translation of a single page

The job translates all the pages of all the groups, but a page can be
translated alone from the list of the pages of a site, with the module
[Internationalisation]: the action `Translate` opens a sidebar with one checkbox
by site, all checked, so some sites can be skipped.

The site of the page itself is the first checkbox and does something different:
the page is translated **in place**, into the locale of its own site. It is used
for a page that was never translated, or that is written in another language
than the one of its site, in particular a copy that was created but not
translated. The language of the page can be selected among the languages of the
sites, or detected by the translation service, that is less reliable with short
strings.

A page written in the language of its own site is not modified: when the
translation of a string is identical to it, nothing is written and the hashes
are stored anyway, so the service is not queried again.

The job is launched from the main settings for all the pages. The copies of a
page are translated automatically when it is created or saved too.

The result of each page is logged in a single summary: translated,
retranslated, partly translated, unchanged, mirror page skipped or failed. The
pages that were not fully translated are listed apart, so they can be checked.


TODO
----

- [x] Divide the sql table internally in two tables, one for strings and one for translations.
- [ ] Check normalization of json-ld.
- [ ] Finalize manual translation; use a tab in resource and a specific menu (see AiGenerator).
- [ ] Store of translated values in resource itself.
- [ ] Use resource template to define properties to translate instead of the main settings.
- [x] Translation of site page blocks of the copied pages of a site group.
- [ ] Translation of the labels of the navigation of the sites.
- [ ] Add options of DeepL Api.
- [ ] Translate html and xml of the values and manage their options (done for the page blocks).
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

Initially created for the digital library, the [Curiothèque] of the [Musée Curie],
completed for the [Musée de Bretagne].


[Translator]: https://gitlab.com/Daniel-KM/Omeka-S-module-Translator
[Omeka S]: https://omeka.org/s
[DeepL]: https://www.deepl.com
[supported languages by DeepL]: https://developers.deepl.com/docs/getting-started/supported-languages
[Common]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[Internationalisation]: https://gitlab.com/Daniel-KM/Omeka-S-module-Internationalisation
[installing a module]: https://omeka.org/s/docs/user-manual/modules
[deepl-php]: https://packagist.org/packages/deeplcom/deepl-php
[Translator.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-Translator/-/releases
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Translator/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Curiothèque]: https://curiotheque.musee.curie.fr/
[Musée Curie]: https://musee.curie.fr
[Musée de Bretagne]: https://collections.musee-bretagne.fr
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
