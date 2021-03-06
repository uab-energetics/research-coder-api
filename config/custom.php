<?php

return [

    'default_user_image' => 'https://image.flaticon.com/icons/svg/149/149071.svg',
    'pagination_max_size' => env('PAGINATION_MAX_SIZE', 500),

    'auth_api_secret' => env('AUTH_API_SECRET'),

    'publication_service' => [
        'host' => env('PUBLICATION_SERVICE_HOST')
    ]

];
