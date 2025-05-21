<?php
session_start();
$conn = new mysqli("localhost", "root", "", "loveconnect");

// Auto-create tables if they don't exist
$conn->query("CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  gender VARCHAR(10),
  age INT,
  location VARCHAR(100),
  interests TEXT,
  profile_photo VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);");

$conn->query("CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT,
  receiver_id INT,
  message TEXT,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);");

// Handle registration
if (isset($_POST["register"])) {
    $name = $_POST["name"];
    $email = $_POST["email"];
    $pass = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $gender = $_POST["gender"];
    $age = $_POST["age"];
    $location = $_POST["location"];
    $interests = $_POST["interests"];
    $photo = $_FILES["photo"]["name"];
    $tmp = $_FILES["photo"]["tmp_name"];
    move_uploaded_file($tmp, "uploads/$photo");

    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, gender, age, location, interests, profile_photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssisss", $name, $email, $pass, $gender, $age, $location, $interests, $photo);
    $stmt->execute();
    echo "Registered successfully. <a href='?login'>Login</a>";
    exit;
}

// Handle login
if (isset($_POST["login"])) {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();
        if (password_verify($password, $user["password"])) {
            $_SESSION["user_id"] = $user["id"];
            header("Location: ?");
            exit;
        } else echo "Wrong password.";
    } else echo "No user found.";
    exit;
}

// Handle message send
if (isset($_POST["send"])) {
    $sender = $_SESSION["user_id"];
    $receiver = $_POST["receiver_id"];
    $msg = $_POST["message"];
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $sender, $receiver, $msg);
    $stmt->execute();
}

// Logout
if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: ?");
    exit;
}

// Main display
if (!isset($_SESSION["user_id"])) {
    // Login or register form
    if (isset($_GET["register"])) {
        echo '<h2>Register</h2>
        <form method="POST" enctype="multipart/form-data">
        <input name="name" placeholder="Full Name" required><br>
        <input name="email" type="email" placeholder="Email" required><br>
        <input name="password" type="password" placeholder="Password" required><br>
        <select name="gender"><option>Male</option><option>Female</option></select><br>
        <input name="age" type="number" placeholder="Age"><br>
        <input name="location" placeholder="Location"><br>
        <input name="interests" placeholder="Interests"><br>
        <input type="file" name="photo" required><br>
        <button name="register">Register</button>
        </form>
        <p><a href="?login">Already have an account? Login</a></p>';
    } else {
        echo '<h2>Login</h2>
        <form method="POST">
        <input name="email" type="email" placeholder="Email"><br>
        <input name="password" type="password" placeholder="Password"><br>
        <button name="login">Login</button>
        </form>
        <p><a href="?register">New here? Register</a></p>';
    }
    exit;
}

// Authenticated section
$user_id = $_SESSION["user_id"];
$me = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
echo "<h2>Welcome, {$me['full_name']}!</h2>";
echo "<img src='uploads/{$me['profile_photo']}' width='100'><br>";
echo "Age: {$me['age']}<br>Gender: {$me['gender']}<br>Location: {$me['location']}<br>Interests: {$me['interests']}<br>";
echo "<a href='?logout'>Logout</a>";

// Match/search section
echo "<h3>Find Matches</h3>";
$gender = $me['gender'] === 'Male' ? 'Female' : 'Male';
$matches = $conn->query("SELECT * FROM users WHERE gender = '$gender' AND id != $user_id");
while ($m = $matches->fetch_assoc()) {
    echo "<div style='border:1px solid #ccc; margin:10px; padding:5px'>";
    echo "<strong>{$m['full_name']}</strong> ({$m['age']}, {$m['location']})<br>";
    echo "<img src='uploads/{$m['profile_photo']}' width='80'><br>";
    echo "<form method='POST'><input type='hidden' name='receiver_id' value='{$m['id']}'>";
    echo "<input name='message' placeholder='Say hi...' required><button name='send'>Send</button></form></div>";
}

// Message inbox
echo "<h3>Inbox</h3>";
$chats = $conn->query("SELECT messages.*, users.full_name FROM messages JOIN users ON users.id = messages.sender_id WHERE receiver_id = $user_id ORDER BY sent_at DESC");
while ($msg = $chats->fetch_assoc()) {
    echo "<p><b>{$msg['full_name']}:</b> {$msg['message']} <i>on {$msg['sent_at']}</i></p>";
}

echo "<hr><p><small>Created by Edwin Kibiwot</small></p>";
?>
