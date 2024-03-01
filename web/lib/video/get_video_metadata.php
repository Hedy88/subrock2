<?php
header("Content-Type: text/json");

$_video = $db->query("SELECT * FROM videos WHERE vid = :vid LIMIT 1", [
    ':vid' => $_GET['video_id']
])->fetch();

/* {
    "user_info": {
        "subscriber_count_string": "\u003cstrong\u003e4,055\u003c\/strong\u003e subscribers", 
        "image_url": "\/\/web.archive.org\/web\/20130301150427\/http:\/\/i2.ytimg.com\/i\/U6lCK7AJfkJQP-e_8c9zOg\/1.jpg?v=506a2dae", 
        "public_name": "migfoxbat", 
        "username": "migfoxbat", 
        "external_id": "U6lCK7AJfkJQP-e_8c9zOg", 
        "subscriber_count": "4,055"}, 
        "video_info": {
            "subscription_ajax_token": "", 
            "view_count_string": 
            "\u003cstrong\u003e41,459\u003c\/strong\u003e views", 
            "dislikes_count_unformatted": 13, 
            "likes_count_unformatted": 94, 
            "likes_dislikes_string": "94 likes, 13 dislikes",
            "view_count": "41,459", 
            "description": "A German built,NASA and The Vatican owned and funded.Infrared Telescope called LUCIFER,for looking at NIBIRU\/NEMESIS\r\nPart 2 5;35 min Where telescope is called LUCIFER"}
        } */
$data = [
    "user_info" => [
        "subscriber_count_string" => "<strong>0</strong> subscribers",
        "image_url" => '/ytd/pfp/'.$_user_helper->GetPFP($_video['author']),
        "public_name" => $_user_helper->GetUsername($_video['author']),
        "username" => $_user_helper->GetUsername($_video['author']),
        "external_id" => $_video['author'], 
        "subscriber_count" => "0"
    ],
    "video_info" => [
        "subscription_ajax_token" => "", 
        "view_count_string" => "<strong>0</strong> views", 
        "dislikes_count_unformatted" => 0, 
        "likes_count_unformatted" => 0, 
        "likes_dislikes_string" => "0 likes, 0 dislikes",
        "view_count" => $db->query("SELECT id FROM views WHERE vid = :vid", [
            ':vid' => $_GET['video_id']
        ])->rowCount(),
        "description" => htmlspecialchars($_video['description'])
    ]
];

$data = json_encode($data, JSON_PRETTY_PRINT);

die(trim($data));
?>