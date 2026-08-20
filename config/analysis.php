<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Analysis Pipeline Thresholds
    |--------------------------------------------------------------------------
    |
    | Tunable parameters of the pull request analysis pipeline: the diff
    | sanitizer budget, the local heuristic weights and the verdict bands
    | that merge heuristic and AI signals into the final assessment.
    |
    */

    'sanitizer' => [
        'max_file_bytes' => (int) env('ANALYSIS_MAX_FILE_BYTES', 300000),
        'context_tokens' => (int) env('ANALYSIS_CONTEXT_TOKENS', 8192),
        'context_ratio' => (float) env('ANALYSIS_CONTEXT_RATIO', 0.6),
        'excluded_paths' => ['vendor/', 'node_modules/', 'dist/', 'build/'],
        'excluded_extensions' => ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'otf', 'mp4', 'mp3', 'pdf', 'zip'],
        'lockfile_keep_lines' => (int) env('ANALYSIS_LOCKFILE_KEEP_LINES', 20),
        'lockfile_patterns' => ['composer.lock', 'package-lock.json', 'yarn.lock', 'pnpm-lock.yaml', 'poetry.lock', 'Gemfile.lock', 'Cargo.lock'],
        'test_asset_paths' => ['/tests/', '/__tests__/', '/test/', '/spec/', '/assets/', '/public/', '/docs/', '/fixtures/', '/mocks/', '/examples/'],
    ],

    'heuristic' => [
        'weights' => [
            'secret' => (int) env('ANALYSIS_WEIGHT_SECRET', 40),
            'downgrade' => (int) env('ANALYSIS_WEIGHT_DOWNGRADE', 25),
            'sensitive' => (int) env('ANALYSIS_WEIGHT_SENSITIVE', 15),
            'eval' => (int) env('ANALYSIS_WEIGHT_EVAL', 15),
            'shell' => (int) env('ANALYSIS_WEIGHT_SHELL', 5),
        ],
        'max_score' => 100,
    ],

    'verdict' => [
        'hostile' => (int) env('ANALYSIS_VERDICT_HOSTILE', 70),
        'flagged' => (int) env('ANALYSIS_VERDICT_FLAGGED', 35),
    ],

    'defcon' => [
        'bands' => [90, 70, 50, 30],
    ],

    'risk' => [
        'high' => (int) env('ANALYSIS_RISK_HIGH', 70),
        'medium' => (int) env('ANALYSIS_RISK_MEDIUM', 35),
    ],

];
