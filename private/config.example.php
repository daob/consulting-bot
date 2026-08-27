<?php
/**
 * Copy to config.php and fill in. This file lives ABOVE the document root,
 * beside lib/ and personas/. Nothing here is ever sent to the browser.
 */
return [
    // ---- Model backend -------------------------------------------------
    // Any OpenAI-compatible endpoint. To move to SURF AI-hub or Scaleway
    // later, change these three lines and nothing else.
    'api_base'  => 'https://openrouter.ai/api/v1',
    'api_key'   => 'sk-or-v1-REPLACE-ME',
    // Measured against the acceptance harness — see docs/configuration.md.
    'model'     => 'google/gemini-2.5-flash-lite',

    // Optional. The client speaks forty times a session and the assessor once,
    // so the chat model is almost the whole bill. Running a cheap model for the
    // conversation and a stronger one for the single assessment costs very
    // little and is where the quality matters most. null = use 'model'.
    'model_assessor' => 'google/gemini-3.7-flash',

    // Passed straight through to the provider. Some models reason on every turn
    // and charge the thinking as output tokens; switching it off, where the
    // provider allows, cuts both the bill and the wait substantially.
    // OpenRouter: ['enabled' => false]. null sends nothing.
    //   qwen3.7-flash  honours it
    //   gemini flash-lite  does not reason anyway
    //   gemini-3.7-flash, glm-5.3-flash  refuse: reasoning is mandatory
    'reasoning' => null,

    // The same, for the single assessor call. Left null on purpose: that call
    // runs once per session and is where reasoning actually earns its cost.
    'reasoning_assessor' => null,

    // Sent by OpenRouter for attribution. Harmless elsewhere.
    'site_url'   => 'https://daob.nl/consult/',
    'site_title' => 'STADS consulting client',

    // Gemini Flash reasons on every turn and counts those tokens against
    // max_tokens, so keep this generous or replies come back empty.
    'max_tokens'          => 1600,
    'max_tokens_assessor' => 8000,
    'temperature'         => 0.85,   // the client: warm, a bit variable
    'temperature_assessor'=> 0.2,    // the assessor: cold

    // ---- Behaviour -----------------------------------------------------
    // 0 disables the whole affect model: no mood in the hidden state, no
    // escalation rules in the prompt, no frustration trace in the report.
    'with_frustration' => 1,

    'persona'   => 'client-01',   // file in personas/
    'max_turns' => 50,            // consultant messages per session
    'max_sessions_per_student' => 3,

    // ---- Access --------------------------------------------------------
    'class_code' => 'CHANGE-ME',
    // One student number per line. Blank lines and #-comments ignored.
    // Set to null to accept any 5-9 digit number (not recommended).
    'students_file' => __DIR__ . '/students.txt',

    // ---- Storage -------------------------------------------------------
    // Must be writable by PHP and outside every document root.
    'data_dir' => __DIR__ . '/data',

    // ---- Housekeeping --------------------------------------------------
    'timezone' => 'Europe/Amsterdam',
    'debug'    => false,   // true adds error detail to API responses
];
