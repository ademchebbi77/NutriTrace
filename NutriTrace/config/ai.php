<?php

/*
|--------------------------------------------------------------------------
| Local AI service (module Transport - Traçabilité)
|--------------------------------------------------------------------------
|
| The Python service in ai/ (FastAPI) runs a fine-tuned Qwen2.5 model and a
| scikit-learn anomaly detector on this machine. Nothing leaves it: no
| external API is called. See ai/README.md to train and start it.
|
| When the service is off, the pages show the answers already cached in the
| database and hide the assistant otherwise.
|
*/

return [

    'enabled' => (bool) env('AI_ENABLED', true),

    'url' => env('AI_SERVICE_URL', 'http://127.0.0.1:8001'),

    // Shared secret sent in the X-AI-Token header, checked by the service when set.
    'token' => env('AI_SERVICE_TOKEN'),

    // Generation on a laptop GPU takes a few seconds; on CPU much longer.
    'timeout' => (int) env('AI_TIMEOUT', 90),

    // How long the "is the service up?" check is remembered, in seconds.
    'health_ttl' => 60,

    'question_max_length' => 300,

];
