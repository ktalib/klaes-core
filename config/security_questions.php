<?php

/*
|--------------------------------------------------------------------------
| Security questions: the sign-in code fallback
|--------------------------------------------------------------------------
|
| Every staff account sets answers to `required` different questions the first
| time it signs in after this went live (RequireSecurityQuestions, after the
| passport photo and phone gates). When a sign-in code has not arrived after
| `fallback_after_minutes`, the code screen offers the questions instead, and
| every one must be answered correctly. SecurityQuestionService holds the rules.
|
| Answers are lower-cased, trimmed and single-spaced, then hashed like a
| password; nobody can read them back and there are no hints. config/ is
| cached: run `php artisan config:cache` after editing this file.
|
*/

return [

    // Off: nobody is asked to set answers and the code screen offers no fallback.
    'enabled' => env('SECURITY_QUESTIONS_ENABLED', true),

    // How many different questions each account answers (user, 2026-11-10: two, not three).
    'required' => 2,

    // Minutes into a sign-in before the code screen offers the questions.
    'fallback_after_minutes' => 10,

    // Wrong tries (per account, not per browser) before the questions lock.
    'max_attempts' => 3,

    // How long the questions stay locked. The sign-in code itself still works.
    'lock_minutes' => 30,

    'min_answer_length' => 2,

    // Refused as answers: too easy to guess. Compared after normalising.
    'blocked_answers' => [
        '1234', '12345', '123456', '0000', 'password', 'pass', 'none', 'nil', 'null', 'na', 'n/a',
        'no', 'yes', 'nothing', 'unknown', 'idk', 'test', 'abc', 'qwerty', 'klaes',
        'nigeria', 'kano', 'kano state', 'abuja', 'lagos',
    ],

    /*
     | Keys are stored with each answer, so never rename or reuse one; add new keys
     | instead. Grouped by category for the dropdowns.
     */
    'questions' => [
        'Childhood & Early Life' => [
            'first_pet' => 'What was the name of your first pet?',
            'childhood_street' => 'What was the name of the street you grew up on?',
            'childhood_nickname' => 'What was your childhood nickname?',
            'birth_hospital' => 'What is the name of the hospital or clinic where you were born?',
            'first_vehicle' => 'What was the make and model of your first vehicle?',
        ],
        'Family & Relatives' => [
            'maternal_grandfather' => 'What is your maternal grandfather’s first name?',
            'paternal_grandmother' => 'What is your paternal grandmother’s first name?',
            'parents_met_town' => 'In what city or town did your parents meet?',
            'favourite_teacher' => 'What was the name of your favorite childhood teacher?',
            'eldest_sibling_middle_name' => 'What is the middle name of your eldest sibling?',
        ],
        'Education & Career' => [
            'primary_school' => 'What was the name of your primary (elementary) school?',
            'first_employer' => 'What was the name of your first employer?',
            'first_company_after_graduation' => 'What was the name of the first company you worked for after graduation?',
            'secondary_best_friend' => 'What was the name of your best friend in secondary school?',
            'final_project_subject' => 'What was the subject of your final year project or thesis?',
        ],
        'Preferences & Milestones' => [
            'first_job_city' => 'What is the name of the city where you had your first job?',
            'first_loved_book' => 'What was the title of the first book you read that you truly loved?',
            'first_live_event' => 'What was the name of the first concert or live event you attended?',
            'first_cinema_movie' => 'What is the name of the first movie you watched in a cinema?',
            'first_roommate' => 'What was the name of your first roommate in university or boarding school?',
        ],
    ],
];
