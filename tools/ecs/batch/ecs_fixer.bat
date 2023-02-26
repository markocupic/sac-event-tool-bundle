:: Run easy-coding-standard (ecs) via this batch file inside your IDE e.g. PhpStorm (Windows only)
:: Install inside PhpStorm the  "Batch Script Support" plugin
cd..
cd..
cd..
cd..
cd..
cd..
php vendor\bin\ecs check vendor/markocupic/sac-event-tool-bundle/src --fix --config vendor/markocupic/sac-event-tool-bundle/tools/ecs/config.php
php vendor\bin\ecs check vendor/markocupic/sac-event-tool-bundle/contao --fix --config vendor/markocupic/sac-event-tool-bundle/tools/ecs/config.php
php vendor\bin\ecs check vendor/markocupic/sac-event-tool-bundle/config --fix --config vendor/markocupic/sac-event-tool-bundle/tools/ecs/config.php
php vendor\bin\ecs check vendor/markocupic/sac-event-tool-bundle/templates --fix --config vendor/markocupic/sac-event-tool-bundle/tools/ecs/config.php
::php vendor\bin\ecs check vendor/markocupic/sac-event-tool-bundle/tests --fix --config vendor/markocupic/sac-event-tool-bundle/tools/ecs/config.php


