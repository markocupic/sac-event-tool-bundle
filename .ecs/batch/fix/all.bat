:: Run easy-coding-standard (ecs) via this batch file inside your IDE e.g. PhpStorm (Windows only)
:: Install inside PhpStorm the  "Batch Script Support" plugin
cd..
cd..
cd..
cd..
cd..
cd..
:: src
vendor\bin\ecs check vendor/markocupic/sac-event-tool-bundle/src --fix --config vendor/markocupic/sac-event-tool-bundle/.ecs/config/default.php
:: tests
vendor\bin\ecs check vendor/markocupic/sac-event-tool-bundle/tests --fix --config vendor/markocupic/sac-event-tool-bundle/.ecs/config/default.php
:: legacy
vendor\bin\ecs check vendor/markocupic/sac-event-tool-bundle/src/Resources/contao --fix --config vendor/markocupic/sac-event-tool-bundle/.ecs/config/legacy.php
:: templates
vendor\bin\ecs check vendor/markocupic/sac-event-tool-bundle/src/Resources/contao/templates --fix --config vendor/markocupic/sac-event-tool-bundle/.ecs/config/template.php
::
cd vendor/markocupic/sac-event-tool-bundle/.ecs./batch/fix
