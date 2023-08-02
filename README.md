Tolge Translation Provider
=============================

Provides [Tolge](https://tolgee.io/) integration for Symfony Translation.

DSN example
-----------

For https:
```
DSN=tolgees://PROJECT_ID:API_KEY@default/FILTER_STATES
```

For http:
```
DSN=tolgee://PROJECT_ID:API_KEY@default/[FILTER_STATES]
```


where:
 - `PROJECT_ID` is your tolgee Project ID
 - `API_KEY` is your Lokalise API key
 - `[FILTER_STATES]` this argument is optional.<br>Filter translations with state.<br>Possible values: `UNTRANSLATED`, `TRANSLATED`, `REVIEWED`<br>[tolgee export API](https://tolgee.io/api#tag/Export/operation/export_1)


You get the project ID from the project URL.

[Generate an API key on Tolge](https://app.tolgee.io/account/apiKeys)
