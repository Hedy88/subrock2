<?php
namespace ChenTube;

class user_helper {
    public function __construct($db)
    {
        $this->db = $db;
    }
    function GetUsername($uuid, $for = 'username')
    {
        $stmt = $this->db->query("SELECT username FROM users WHERE uuid = :uuid", [
            ':uuid' => $uuid
        ])->fetch();

            return (isset($stmt['username'])) ? htmlspecialchars($stmt['username']) : "[Deleted]";
    }

    function GetUsernameUUID($username)
    {
        $stmt = $this->db->query("SELECT uuid FROM users WHERE lower(username) = lower(:username)", [
            ':username' => $username
        ])->fetch();

            return (isset($stmt['uuid'])) ? htmlspecialchars($stmt['uuid']) : "";
    }

    function GetPFP($uuid)
    {
        $stmt = $this->db->query("SELECT pfp FROM users WHERE uuid = :uuid", [
            ':uuid' => $uuid
        ])->fetch();

            return (isset($stmt['pfp'])) ? htmlspecialchars($stmt['pfp']) : "no_videos.png";
    }

    function uploaded_vcount($uuid) {
        $stmt = $this->db->query("SELECT id FROM videos WHERE author = :uuid", [
            ':uuid' => $uuid
        ]);
        return $stmt->rowCount();
    }

    function upload_cooldown($uuid) {
        $stmt = $this->db->query("SELECT id FROM users WHERE uuid = :uuid AND upload_cooldown >= NOW() - INTERVAL 5 MINUTE LIMIT 1", [
            ':uuid' => $uuid
        ]);
        return $stmt->rowCount() === 1;
    }
}
?>