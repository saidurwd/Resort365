<?php

/*
| Bangla validation messages for the public website's forms (Step 8.2b). Only the rules those forms use; anything else falls back
| to English.
*/

return [
    'accepted' => ':attribute মেনে নিতে হবে।',
    'after' => ':attribute অবশ্যই :date-এর পরের তারিখ হতে হবে।',
    'after_or_equal' => ':attribute আজ বা পরের তারিখ হতে হবে।',
    'before_or_equal' => ':attribute অবশ্যই :date বা তার আগের তারিখ হতে হবে।',
    'date_format' => ':attribute সঠিক তারিখ নয়।',
    'email' => ':attribute সঠিক ইমেইল ঠিকানা নয়।',
    'enum' => 'বেছে নেওয়া :attribute সঠিক নয়।',
    'exists' => 'বেছে নেওয়া :attribute সঠিক নয়।',
    'in' => 'বেছে নেওয়া :attribute সঠিক নয়।',
    'integer' => ':attribute একটি সংখ্যা হতে হবে।',
    'max' => ['string' => ':attribute :max অক্ষরের বেশি হতে পারবে না।', 'numeric' => ':attribute :max-এর বেশি হতে পারবে না।'],
    'min' => ['string' => ':attribute অন্তত :min অক্ষরের হতে হবে।', 'numeric' => ':attribute অন্তত :min হতে হবে।'],
    'prohibited' => 'আবার চেষ্টা করুন।',
    'regex' => ':attribute সঠিক নয়।',
    'required' => ':attribute দিতে হবে।',
    'required_with' => ':attribute দিতে হবে।',
    'size' => ['string' => ':attribute :size অক্ষরের হতে হবে।'],

    'attributes' => [
        'check_in' => 'পৌঁছানোর তারিখ',
        'check_out' => 'চলে যাওয়ার তারিখ',
        'adults' => 'প্রাপ্তবয়স্ক',
        'children' => 'শিশু',
        'first_name' => 'নামের প্রথম অংশ',
        'last_name' => 'নামের শেষ অংশ',
        'email' => 'ইমেইল',
        'phone' => 'মোবাইল নম্বর',
        'nationality_code' => 'জাতীয়তা',
        'special_requests' => 'বিশেষ অনুরোধ',
        'pay_choice' => 'পরিশোধের ধরন',
        'accept_policies' => 'নীতিমালা',
    ],
];
