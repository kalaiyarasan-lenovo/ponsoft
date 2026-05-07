<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Content-Type: application/json");
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? '';

if ($action === 'add' || $action === 'edit') {
    $years_array = $_POST['Year_Paid'] ?? [];
    $year_paid_str = implode(',', $years_array);
    

    $mobile = trim($_POST['Mobile_Number'] ?? '');
    if (!empty($mobile) && (strlen($mobile) > 10 || !ctype_digit($mobile))) {
        $_SESSION['flash_error'] = "Mobile number must be at most 10 digits and numbers only.";
        header("Location: ../dashboard.php");
        exit();
    }
    $email = trim($_POST['Email'] ?? '');
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_error'] = "Invalid Email format.";
        header("Location: ../dashboard.php");
        exit();
    }

    $name = trim($_POST['Name'] ?? '');
    $p1 = (float)($_POST['Payment_1'] ?? 0);
    $p2 = (float)($_POST['Payment_2'] ?? 0);
    $p3 = (float)($_POST['Payment_3'] ?? 0);

    if (empty($name) && empty($mobile) && empty($years_array) && $p1 == 0 && $p2 == 0 && $p3 == 0) {
        $_SESSION['flash_error'] = "Please provide at least one of the following: Name, Mobile Number, or Payment details.";
        header("Location: ../dashboard.php");
        exit();
    }
}

if ($action === 'add') {
    try {
        $stmt = $conn->prepare("INSERT INTO members (Code, Name, Father_Name, Address_1, Address_2, Address_3, Address_4, City, District, State, Mobile_Number, Email, Sex, VIP, Payment_1, Payment_2, Payment_3, Country, PinCode, Year_Paid, Language) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $lang = $_POST['Language'] ?? '';
        if (empty($lang)) {
            $lang = ($_POST['Country'] === ',e;jpah') ? 'Tamil' : 'English';
        }
        
        $stmt->execute([
            $_POST['Code'] ?? '',
            $_POST['Name'] ?? '',
            $_POST['Father_Name'] ?? '',
            $_POST['Address_1'] ?? '',
            $_POST['Address_2'] ?? '',
            $_POST['Address_3'] ?? '',
            $_POST['Address_4'] ?? '',
            $_POST['City'] ?? '',
            $_POST['District'] ?? '',
            $_POST['State'] ?? '',
            $_POST['Mobile_Number'] ?? '',
            $email,
            $_POST['Sex'] ?? '',
            $_POST['VIP'] ?? '',
            $_POST['Payment_1'] ?: 0,
            $_POST['Payment_2'] ?: 0,
            $_POST['Payment_3'] ?: 0,
            $_POST['Country'] ?? '',
            $_POST['PinCode'] ?? '',
            $year_paid_str,
            $lang
        ]);

        $_SESSION['flash_success'] = "Member added successfully!";
        header("Location: ../dashboard.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Error adding member: " . $e->getMessage();
        header("Location: ../dashboard.php");
        exit();
    }
}

if ($action === 'edit') {
    try {
        $stmt = $conn->prepare("UPDATE members SET Name = ?, Father_Name = ?, Address_1 = ?, Address_2 = ?, Address_3 = ?, Address_4 = ?, City = ?, District = ?, State = ?, Mobile_Number = ?, Email = ?, Sex = ?, VIP = ?, Payment_1 = ?, Payment_2 = ?, Payment_3 = ?, Country = ?, PinCode = ?, Year_Paid = ?, Language = ? WHERE Code = ?");
        
        $lang = $_POST['Language'] ?? '';
        if (empty($lang)) {
            $lang = ($_POST['Country'] === ',e;jpah') ? 'Tamil' : 'English';
        }
        
        $stmt->execute([
            $_POST['Name'] ?? '',
            $_POST['Father_Name'] ?? '',
            $_POST['Address_1'] ?? '',
            $_POST['Address_2'] ?? '',
            $_POST['Address_3'] ?? '',
            $_POST['Address_4'] ?? '',
            $_POST['City'] ?? '',
            $_POST['District'] ?? '',
            $_POST['State'] ?? '',
            $_POST['Mobile_Number'] ?? '',
            $email,
            $_POST['Sex'] ?? '',
            $_POST['VIP'] ?? '',
            $_POST['Payment_1'] ?: 0,
            $_POST['Payment_2'] ?: 0,
            $_POST['Payment_3'] ?: 0,
            $_POST['Country'] ?? '',
            $_POST['PinCode'] ?? '',
            $year_paid_str,
            $lang,
            $_POST['Code']
        ]);

        $_SESSION['flash_success'] = "Member updated successfully!";
        header("Location: ../dashboard.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Error updating member: " . $e->getMessage();
        header("Location: ../dashboard.php");
        exit();
    }
}

if ($action === 'quick_pay') {
    $code = $_POST['code'] ?? '';
    $year = $_POST['year'] ?? '';
    
    header("Content-Type: application/json");
    if (!$code || !$year) {
        echo json_encode(['success' => false, 'message' => 'Missing code or year']);
        exit();
    }

    try {
        $stmt = $conn->prepare("SELECT Year_Paid FROM members WHERE Code = ?");
        $stmt->execute([$code]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($member) {
            $years = $member['Year_Paid'] ? explode(',', $member['Year_Paid']) : [];
            $years = array_map('trim', $years);
            
            if (!in_array((string)$year, $years)) {
                $years[] = (string)$year;
                rsort($years); // Optional: sort years in descending order
                $new_years_str = implode(',', $years);
                
                $update = $conn->prepare("UPDATE members SET Year_Paid = ? WHERE Code = ?");
                $update->execute([$new_years_str, $code]);
                
                echo json_encode(['success' => true, 'message' => "Payment for year $year recorded successfully"]);
            } else {
                echo json_encode(['success' => true, 'message' => "Payment for year $year is already recorded"]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Member not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

if ($action === 'quick_pay_multiple') {
    $code = $_POST['code'] ?? '';
    $years_array = $_POST['years'] ?? [];
    
    header("Content-Type: application/json");
    if (!$code) {
        echo json_encode(['success' => false, 'message' => 'Missing code']);
        exit();
    }

    try {
        // Optional validation: check if years are in valid range
        
        // Sort years descending
        rsort($years_array);
        $new_years_str = implode(',', $years_array);
        
        $update = $conn->prepare("UPDATE members SET Year_Paid = ? WHERE Code = ?");
        $update->execute([$new_years_str, $code]);
        
        echo json_encode(['success' => true, 'message' => 'Payments updated successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

if ($action === 'delete') {
    $code = $_GET['code'] ?? '';
    if (!$code) {
        header("Location: ../dashboard.php");
        exit();
    }

    try {
        $stmt = $conn->prepare("DELETE FROM members WHERE Code = ?");
        $stmt->execute([$code]);

        $_SESSION['flash_success'] = "Member deleted successfully!";
        header("Location: ../dashboard.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Error deleting member: " . $e->getMessage();
        header("Location: ../dashboard.php");
        exit();
    }
}

if ($action === 'get') {
    $code = $_GET['code'] ?? '';
    header("Content-Type: application/json");
    if (!$code) {
        echo json_encode(['success' => false, 'message' => 'Missing code']);
        exit();
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM members WHERE Code = ?");
        $stmt->execute([$code]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($member) {
            echo json_encode(['success' => true, 'data' => $member]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Member not found']);
        }
        exit();
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}
