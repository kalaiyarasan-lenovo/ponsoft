<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get filter values
$code_filter = isset($_GET['code']) ? trim($_GET['code']) : '';
$mobile_filter = isset($_GET['mobile']) ? trim($_GET['mobile']) : '';
$country_filter = isset($_GET['country']) ? trim($_GET['country']) : '';
$city_filter = isset($_GET['city']) ? trim($_GET['city']) : '';
$district_filter = isset($_GET['district']) ? trim($_GET['district']) : '';
$state_filter = isset($_GET['state']) ? trim($_GET['state']) : '';
$year_filter = isset($_GET['year']) ? trim($_GET['year']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_clauses = [];
$params = [];

if (!empty($code_filter)) {
    $where_clauses[] = "Code = ?";
    $params[] = $code_filter;
}

if (!empty($mobile_filter)) {
    $where_clauses[] = "Mobile_Number LIKE ?";
    $params[] = "%$mobile_filter%";
}

if (!empty($country_filter)) {
    $where_clauses[] = "Country = ?";
    $params[] = $country_filter;
}

if (!empty($city_filter)) {
    $where_clauses[] = "City = ?";
    $params[] = $city_filter;
}

if (!empty($district_filter)) {
    $where_clauses[] = "District = ?";
    $params[] = $district_filter;
}

if (!empty($state_filter)) {
    $where_clauses[] = "State = ?";
    $params[] = $state_filter;
}

if (!empty($year_filter)) {
    if ($status_filter === 'unpaid') {
        $where_clauses[] = "(Year_Paid NOT LIKE ? OR Year_Paid IS NULL)";
        $params[] = "%$year_filter%";
    } else {
        $where_clauses[] = "Year_Paid LIKE ?";
        $params[] = "%$year_filter%";
    }
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Fetch total count and members
try {
    // Count total found
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM members $where_sql");
    $count_stmt->execute($params);
    $total_found = $count_stmt->fetchColumn();

    // Fetch paginated list
    $stmt = $conn->prepare("SELECT * FROM members $where_sql ORDER BY Code ASC LIMIT ? OFFSET ?");
    foreach ($params as $i => $p) {
        $stmt->bindValue($i + 1, $p);
    }
    $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $members = $stmt->fetchAll();
    
    $total_pages = ceil($total_found / $limit);

    // Fetch the next available code
    $next_code_stmt = $conn->query("SELECT MAX(CAST(Code AS UNSIGNED)) FROM members");
    $max_code = $next_code_stmt->fetchColumn();
    $next_code = ($max_code) ? (int)$max_code + 1 : 1;

    // Fetch unique values for dropdown filters (Cascading)
    $countries = $conn->query("SELECT DISTINCT Country FROM members WHERE Country != '' AND Country IS NOT NULL ORDER BY Country ASC")->fetchAll(PDO::FETCH_COLUMN);

    $state_sql = "SELECT DISTINCT State FROM members WHERE State != '' AND State IS NOT NULL";
    if (!empty($country_filter)) $state_sql .= " AND Country = " . $conn->quote($country_filter);
    $states = $conn->query($state_sql . " ORDER BY State ASC")->fetchAll(PDO::FETCH_COLUMN);

    $dist_sql = "SELECT DISTINCT District FROM members WHERE District != '' AND District IS NOT NULL";
    if (!empty($state_filter)) {
        $dist_sql .= " AND State = " . $conn->quote($state_filter);
    } elseif (!empty($country_filter)) {
        $dist_sql .= " AND Country = " . $conn->quote($country_filter);
    }
    $districts = $conn->query($dist_sql . " ORDER BY District ASC")->fetchAll(PDO::FETCH_COLUMN);

    $city_sql = "SELECT DISTINCT City FROM members WHERE City != '' AND City IS NOT NULL";
    if (!empty($district_filter)) {
        $city_sql .= " AND District = " . $conn->quote($district_filter);
    } elseif (!empty($state_filter)) {
        $city_sql .= " AND State = " . $conn->quote($state_filter);
    } elseif (!empty($country_filter)) {
        $city_sql .= " AND Country = " . $conn->quote($country_filter);
    }
    $cities = $conn->query($city_sql . " ORDER BY City ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $error_msg = "Error fetching members: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Ponsoft Management</title>
    <link rel="stylesheet" href="assets/css/style.css?v=1.1">
</head>
<body class="dashboard-layout">
    <nav class="navbar">
        <div class="nav-brand">
            <div class="nav-logo">P</div>
            <h1>Ponsoft</h1>
        </div>
        <div class="user-nav">
            <div class="user-badge">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?></div>
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <span class="user-role">Administrator</span>
                </div>
            </div>
            <a href="logout.php" class="btn-logout-small">Sign Out</a>
        </div>
    </nav>

    <main class="main-content">
        <div class="content-header-main" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <div>
                <h3 style="font-size: 1.5rem; color: var(--text-main); margin: 0;">Members Directory</h3>
                <?php if (!empty($code_filter)): ?>
                    <p style="font-size: 0.9rem; color: var(--text-secondary); margin-top: 5px;">
                        Filtering by Code: "<strong><?php echo htmlspecialchars($code_filter); ?></strong>"
                    </p>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 15px; align-items: center;">
                <div class="stats" style="background: white; padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border); font-size: 0.9rem;">
                    <span style="color: var(--text-muted);">Total Records:</span> <strong style="color: var(--primary);"><?php echo number_format($total_found); ?></strong>
                </div>
                <button class="btn btn-primary" onclick="openAddModal()" style="padding: 10px 24px; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 1.2rem; line-height: 1;">+</span> Add New Member
                </button>
            </div>
        </div>

        <?php if (isset($_SESSION['flash_success'])): ?>
            <div id="flash-success" class="success-message" style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; border: 1px solid #bbf7d0;">
                <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
            </div>
            <script>
                setTimeout(() => {
                    const msg = document.getElementById('flash-success');
                    if (msg) {
                        msg.style.transition = 'opacity 0.5s ease';
                        msg.style.opacity = '0';
                        setTimeout(() => msg.remove(), 500);
                    }
                }, 2000);
            </script>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="error-message" style="margin-bottom: 1.5rem;">
                <?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
            </div>
        <?php endif; ?>

        <div class="filter-card" style="background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <div style="display: flex; align-items: center; gap: 8px; color: var(--text-main); font-weight: 600; font-size: 0.95rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                    Quick Filters
                </div>
                <?php if (!empty($code_filter) || !empty($mobile_filter) || !empty($country_filter) || !empty($city_filter) || !empty($district_filter) || !empty($state_filter) || !empty($year_filter) || !empty($status_filter)): ?>
                    <a href="dashboard.php" class="btn-clear" style="font-size: 0.8rem; padding: 4px 12px; background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb;">Clear All Filters</a>
                <?php endif; ?>
            </div>

            <form action="" method="GET" id="filterForm" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">


                <div class="filter-group">
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase;">Member Code</label>
                    <input type="text" name="code" id="codeInput" class="search-input" placeholder="e.g. 1234" value="<?php echo htmlspecialchars($code_filter); ?>" autocomplete="off" oninput="debouncedSubmit()" style="width: 100%; border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; font-size: 0.9rem;">
                </div>

                <div class="filter-group">
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase;">Mobile Number</label>
                    <input type="text" name="mobile" id="mobileInput" class="search-input" placeholder="10 digits..." maxlength="10" value="<?php echo htmlspecialchars($mobile_filter); ?>" autocomplete="off" oninput="this.value = this.value.replace(/[^0-9]/g, ''); debouncedSubmit()" style="width: 100%; border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; font-size: 0.9rem;">
                </div>

                <div class="filter-group">
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase;">Country</label>
                    <select name="country" id="countryInput" class="select-input" onchange="document.getElementById('stateInput').value=''; document.getElementById('districtInput').value=''; document.getElementById('cityInput').value=''; this.form.submit()" style="width: 100%; border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; font-size: 0.9rem; height: 38px;">
                        <option value="">All Countries</option>
                        <?php foreach($countries as $cnt): ?>
                            <option value="<?php echo htmlspecialchars($cnt); ?>" <?php echo ($country_filter == $cnt) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cnt); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase;">State</label>
                    <select name="state" id="stateInput" class="select-input" onchange="document.getElementById('districtInput').value=''; document.getElementById('cityInput').value=''; this.form.submit()" style="width: 100%; border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; font-size: 0.9rem; height: 38px;">
                        <option value="">All States</option>
                        <?php foreach($states as $s): ?>
                            <option value="<?php echo htmlspecialchars($s); ?>" <?php echo ($state_filter == $s) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase;">District</label>
                    <select name="district" id="districtInput" class="select-input" onchange="document.getElementById('cityInput').value=''; this.form.submit()" style="width: 100%; border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; font-size: 0.9rem; height: 38px;">
                        <option value="">All Districts</option>
                        <?php foreach($districts as $d): ?>
                            <option value="<?php echo htmlspecialchars($d); ?>" <?php echo ($district_filter == $d) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase;">City or Taluk</label>
                    <select name="city" id="cityInput" class="select-input" onchange="this.form.submit()" style="width: 100%; border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; font-size: 0.9rem; height: 38px;">
                        <option value="">All Cities</option>
                        <?php foreach($cities as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($city_filter == $c) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase;">Payment Year</label>
                    <select name="year" class="select-input" onchange="this.form.submit()" style="width: 100%; border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; font-size: 0.9rem; height: 38px;">
                        <option value="">All Years</option>
                        <?php 
                            $current_y = date("Y");
                            for($y = $current_y + 1; $y >= 2000; $y--) {
                                $selected = ($year_filter == $y) ? 'selected' : '';
                                echo "<option value=\"$y\" $selected>$y</option>";
                            }
                        ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase;">Status</label>
                    <select name="status" class="select-input" onchange="this.form.submit()" style="width: 100%; border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; font-size: 0.9rem; height: 38px;">
                        <option value="paid" <?php echo ($status_filter !== 'unpaid') ? 'selected' : ''; ?>>Paid Members</option>
                        <option value="unpaid" <?php echo ($status_filter === 'unpaid') ? 'selected' : ''; ?>>Unpaid Members</option>
                    </select>
                </div>
            </form>
        </div>

        <?php if (isset($error_msg)): ?>
            <div class="error-message"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="table-container">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th style="white-space: nowrap;">Last Year Paid</th>
                            <th>Father's Name</th>
                            <th>Address</th>
                            <th>City</th>
                            <th>District</th>
                            <th>State</th>
                            <th>Email</th>
                            <th>Sex</th>
                            <th>Payment 1</th>
                            <th>Payment 2</th>
                            <th>Payment 3</th>
                            <th>Country</th>
                            <th>PinCode</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($members) > 0): ?>
                            <?php foreach ($members as $member): ?>
                                <tr class="clickable-row" onclick="openEditModal('<?php echo $member['Code']; ?>')" <?php echo ($total_found === 1) ? 'style="background: #f0fdf4;"' : ''; ?>>
                                    <td>
                                        <strong><?php echo htmlspecialchars($member['Code'] ?? '-'); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($member['Name'] ?? '-'); ?> 
                                        <?php if (($member['VIP'] ?? '') === 'Yes'): ?>
                                            <span style="color: #b45309; font-weight: 700; font-size: 0.75rem; background: #fef3c7; padding: 1px 4px; border-radius: 4px; margin-left: 4px;" title="VIP Member">V</span>
                                        <?php else: ?>
                                            <span style="color: #1d4ed8; font-weight: 700; font-size: 0.75rem; background: #dbeafe; padding: 1px 4px; border-radius: 4px; margin-left: 4px;" title="Regular Member">R</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($member['Mobile_Number'] ?? '-'); ?></td>
                                    <td>
                                        <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                            <?php 
                                            $years = explode(',', $member['Year_Paid'] ?? '');
                                            foreach($years as $year) {
                                                if(trim($year)) {
                                                    echo '<span class="badge" style="background: #e0f2fe; color: #0369a1; font-size: 10px; padding: 2px 6px;">' . htmlspecialchars(trim($year)) . '</span>';
                                                }
                                            }
                                            if(empty(array_filter($years))) echo '<span style="color: var(--text-muted);">None</span>';
                                            ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($member['Father_Name'] ?? '-'); ?></td>
                                    <td>
                                        <?php 
                                            $address_parts = array_filter([
                                                trim($member['Address_1'] ?? ''), 
                                                trim($member['Address_2'] ?? ''), 
                                                trim($member['Address_3'] ?? ''), 
                                                trim($member['Address_4'] ?? '')
                                            ]);
                                            echo htmlspecialchars(implode(', ', $address_parts)) ?: '-';
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($member['City'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($member['District'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($member['State'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($member['Email'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($member['Sex'] ?? '-'); ?></td>
                                    <td><?php echo number_format($member['Payment_1'] ?? 0, 2); ?></td>
                                    <td><?php echo number_format($member['Payment_2'] ?? 0, 2); ?></td>
                                    <td><?php echo number_format($member['Payment_3'] ?? 0, 2); ?></td>
                                    <td><?php echo htmlspecialchars($member['Country'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($member['PinCode'] ?? '-'); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon" onclick="event.stopPropagation(); openPaidModal('<?php echo $member['Code']; ?>', <?php echo htmlspecialchars(json_encode($member['Name'] ?? '')); ?>)" style="color: #059669; border-color: #059669;" title="Record Payment">
                                                <span>💰</span>
                                            </button>
                                            <button class="btn-icon" title="View Profile">
                                                <span>✏️</span>
                                            </button>
                                            <a href="includes/member_actions.php?action=delete&code=<?php echo $member['Code']; ?>" 
                                               onclick="event.stopPropagation(); return confirm('Are you sure you want to delete this member?')" 
                                               class="btn-icon delete" title="Delete Member">
                                                <span>🗑️</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="17" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    No members found matching your search.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div class="pagination-info">
                    Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_found); ?></strong> of <strong><?php echo number_format($total_found); ?></strong> entries
                </div>
                <div class="pagination-controls">
                    <?php 
                        $base_url = "?code=" . urlencode($code_filter) . "&mobile=" . urlencode($mobile_filter) . "&country=" . urlencode($country_filter) . "&city=" . urlencode($city_filter) . "&district=" . urlencode($district_filter) . "&state=" . urlencode($state_filter) . "&year=" . urlencode($year_filter) . "&status=" . urlencode($status_filter) . "&";
                        $range = 2; // Number of pages to show around current page
                    ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="<?php echo $base_url; ?>page=1" class="page-link" title="First Page">&laquo;</a>
                        <a href="<?php echo $base_url; ?>page=<?php echo $page - 1; ?>" class="page-link">Prev</a>
                    <?php endif; ?>

                    <?php
                        for ($i = 1; $i <= $total_pages; $i++) {
                            if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)) {
                                if ($i == $page) {
                                    echo '<span class="current-page">' . $i . '</span>';
                                } else {
                                    echo '<a href="' . $base_url . 'page=' . $i . '" class="page-link">' . $i . '</a>';
                                }
                            } elseif ($i == $page - $range - 1 || $i == $page + $range + 1) {
                                echo '<span class="pagination-ellipsis">...</span>';
                            }
                        }
                    ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo $base_url; ?>page=<?php echo $page + 1; ?>" class="page-link">Next</a>
                        <a href="<?php echo $base_url; ?>page=<?php echo $total_pages; ?>" class="page-link" title="Last Page">&raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Add/Edit Member Modal -->
    <div id="memberModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding-right: 3rem; gap: 10px;">
                <h2 id="modalTitle">Member Profile</h2>
                <div style="display: flex; gap: 10px;">
                    <button type="button" id="editToggleBtn" class="btn btn-secondary" onclick="enableEditing()">✏️ Edit Details</button>
                </div>
            </div>
            <form id="memberForm" action="includes/member_actions.php?action=add" method="POST" onsubmit="return validateMemberForm()">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="m_Code">Code</label>
                        <input type="text" name="Code" id="m_Code" required>
                    </div>
                    <div class="form-group">
                        <label for="m_Name">Full Name</label>
                        <input type="text" name="Name" id="m_Name" required>
                    </div>
                    <div class="form-group">
                        <label for="m_Father_Name">Father's Name</label>
                        <input type="text" name="Father_Name" id="m_Father_Name">
                    </div>
                    
                    <div class="form-group">
                        <label for="m_Sex">Sex</label>
                        <select name="Sex" id="m_Sex" class="select-input">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="m_VIP">VIP Status</label>
                        <select name="VIP" id="m_VIP" class="select-input">
                            <option value="No">No (Regular)</option>
                            <option value="Yes">Yes (VIP)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="m_Mobile_Number">Mobile Number</label>
                        <input type="text" name="Mobile_Number" id="m_Mobile_Number" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                    <div class="form-group">
                        <label for="m_Email">Email</label>
                        <input type="email" name="Email" id="m_Email" placeholder="example@domain.com" maxlength="100">
                    </div>

                    <div class="form-group">
                        <label for="m_Address_1">Address 1</label>
                        <input type="text" name="Address_1" id="m_Address_1">
                    </div>
                    <div class="form-group">
                        <label for="m_Address_2">Address 2</label>
                        <input type="text" name="Address_2" id="m_Address_2">
                    </div>
                    <div class="form-group">
                        <label for="m_Address_3">Address 3</label>
                        <input type="text" name="Address_3" id="m_Address_3">
                    </div>
                    <div class="form-group">
                        <label for="m_Address_4">Address 4</label>
                        <input type="text" name="Address_4" id="m_Address_4">
                    </div>
                    
                    <div class="form-group">
                        <label for="m_City">City</label>
                        <input type="text" name="City" id="m_City">
                    </div>
                    <div class="form-group">
                        <label for="m_District">District</label>
                        <input type="text" name="District" id="m_District">
                    </div>
                    <div class="form-group">
                        <label for="m_State">State</label>
                        <input type="text" name="State" id="m_State" value="Tamil Nadu">
                    </div>

                    <div class="form-group">
                        <label for="m_Country">Country</label>
                        <input type="text" name="Country" id="m_Country" value="India">
                    </div>
                    <div class="form-group">
                        <label for="m_PinCode">PinCode</label>
                        <input type="text" name="PinCode" id="m_PinCode">
                    </div>
                    <div class="form-group full-width" id="yearSelectionSection">
                        <label>Last Year Paid (Select all that apply)</label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px; padding: 10px; background: #f9fafb; border: 1px solid var(--border); border-radius: 6px; max-height: 150px; overflow-y: auto;">
                            <?php 
                                $current_year = date("Y");
                                for($y = $current_year + 1; $y >= 2000; $y--) {
                                    echo "<label style='display: flex; align-items: center; gap: 6px; font-size: 0.85rem; cursor: pointer;'>";
                                    echo "<input type='checkbox' name='Year_Paid[]' value='$y' class='year-checkbox'> $y";
                                    echo "</label>";
                                }
                            ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="m_Payment_1">Payment 1</label>
                        <input type="number" step="0.01" name="Payment_1" id="m_Payment_1" value="0.00">
                    </div>
                    <div class="form-group">
                        <label for="m_Payment_2">Payment 2</label>
                        <input type="number" step="0.01" name="Payment_2" id="m_Payment_2" value="0.00">
                    </div>
                    <div class="form-group">
                        <label for="m_Payment_3">Payment 3</label>
                        <input type="number" step="0.01" name="Payment_3" id="m_Payment_3" value="0.00">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Save Member</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick Payment Modal -->
    <div id="quickPaymentModal" class="modal" style="z-index: 1050;">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <span class="close-modal" onclick="closeQuickPaymentModal()">&times;</span>
            <h3 style="margin-bottom: 20px;">Record Payment</h3>
            <p style="margin-bottom: 20px; color: var(--text-secondary); line-height: 1.5;">Select the year for member code: <strong id="qp_memberCode"></strong><br><strong id="qp_memberName" style="color: var(--text-primary);"></strong></p>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px; padding: 10px; background: #f9fafb; border: 1px solid var(--border); border-radius: 6px; max-height: 150px; overflow-y: auto; text-align: left;">
                <?php 
                    $current_year = date("Y");
                    for($y = $current_year + 1; $y >= 2000; $y--) {
                        echo "<label style='display: flex; align-items: center; gap: 6px; font-size: 0.85rem; cursor: pointer;'>";
                        echo "<input type='checkbox' name='qp_Year_Paid[]' value='$y' class='qp-year-checkbox'> $y";
                        echo "</label>";
                    }
                ?>
            </div>
            <div style="margin-top: 15px;">
                <button class="btn btn-primary" onclick="submitQuickPayment()">Save Payment</button>
            </div>
        </div>
    </div>

    <script>
        const mobileInput = document.getElementById('mobileInput');
        const codeSearchInput = document.getElementById('codeInput');

        // Force field values from URL params — prevents browser from restoring stale values on F5
        const urlParams = new URLSearchParams(window.location.search);
        if (codeSearchInput) codeSearchInput.value = urlParams.get('code') || '';
        if (mobileInput) mobileInput.value = urlParams.get('mobile') || '';
        if (document.getElementById('countryInput')) document.getElementById('countryInput').value = urlParams.get('country') || '';
        if (document.getElementById('stateInput')) document.getElementById('stateInput').value = urlParams.get('state') || '';

        // Detect browser refresh (F5) vs filter-triggered reload
        const navType = performance.getEntriesByType('navigation')[0]?.type;
        if (navType === 'reload') {
            // Browser refresh — clear saved field so focus defaults to code
            sessionStorage.removeItem('lastFocusedFilter');
        }

        // Track which field the user is actively typing in
        if (codeSearchInput) codeSearchInput.addEventListener('focus', () => sessionStorage.setItem('lastFocusedFilter', 'codeInput'));
        if (mobileInput)     mobileInput.addEventListener('focus',  () => sessionStorage.setItem('lastFocusedFilter', 'mobileInput'));
        if (document.getElementById('cityInput'))     document.getElementById('cityInput').addEventListener('focus',     () => sessionStorage.setItem('lastFocusedFilter', 'cityInput'));
        if (document.getElementById('districtInput')) document.getElementById('districtInput').addEventListener('focus', () => sessionStorage.setItem('lastFocusedFilter', 'districtInput'));
        if (document.getElementById('stateInput'))    document.getElementById('stateInput').addEventListener('focus',    () => sessionStorage.setItem('lastFocusedFilter', 'stateInput'));

        let timeout = null;
        function debouncedSubmit() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 600);
        }

        // Restore focus to whichever field was last used
        const savedField = sessionStorage.getItem('lastFocusedFilter');
        if (savedField) {
            const field = document.getElementById(savedField);
            if (field) {
                const val = field.value;
                field.value = '';
                field.focus();
                field.value = val;
            }
        } else if (codeSearchInput) {
            const val = codeSearchInput.value;
            codeSearchInput.value = '';
            codeSearchInput.focus();
            codeSearchInput.value = val;
        }

        // Modal Logic
        const modal = document.getElementById('memberModal');
        const form = document.getElementById('memberForm');
        const modalTitle = document.getElementById('modalTitle');
        const codeInput = document.getElementById('m_Code');
        const editToggleBtn = document.getElementById('editToggleBtn');
        const submitBtn = document.getElementById('submitBtn');

        function setFieldsDisabled(disabled) {
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                if (input.id === 'm_Code' && form.action.includes('action=edit')) {
                    input.readOnly = true;
                } else {
                    input.disabled = disabled;
                }
            });
            submitBtn.style.display = disabled ? 'none' : 'block';
        }

        function enableEditing() {
            setFieldsDisabled(false);
            editToggleBtn.style.display = 'none';
            modalTitle.innerText = 'Update Member: ' + codeInput.value;
        }

        const qpModal = document.getElementById('quickPaymentModal');
        let currentQpCode = null;

        function openPaidModal(code, name) {
            currentQpCode = code;
            document.getElementById('qp_memberCode').innerText = code;
            document.getElementById('qp_memberName').innerText = name ? name : '';
            
            // Clear checkboxes
            document.querySelectorAll('.qp-year-checkbox').forEach(cb => cb.checked = false);

            fetch(`includes/member_actions.php?action=get&code=${code}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const m = data.data;
                        const selectedYears = (m.Year_Paid || '').split(',').map(y => y.trim());
                        document.querySelectorAll('.qp-year-checkbox').forEach(cb => {
                            cb.checked = selectedYears.includes(cb.value);
                        });
                        qpModal.style.display = 'block';
                    } else {
                        alert('Error fetching details');
                    }
                });
        }

        function closeQuickPaymentModal() {
            qpModal.style.display = 'none';
            currentQpCode = null;
        }

        function submitQuickPayment() {
            if (!currentQpCode) return;
            
            const selectedYears = Array.from(document.querySelectorAll('.qp-year-checkbox:checked')).map(cb => cb.value);
            
            const formData = new FormData();
            formData.append('code', currentQpCode);
            // Even if empty, we send it to overwrite with empty
            selectedYears.forEach(year => {
                formData.append('years[]', year);
            });

            fetch('includes/member_actions.php?action=quick_pay_multiple', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
                closeQuickPaymentModal();
            })
            .catch(err => {
                alert('An error occurred');
                closeQuickPaymentModal();
            });
        }

        const nextCode = <?php echo $next_code; ?>;

        function openAddModal() {
            form.reset();
            form.action = 'includes/member_actions.php?action=add';
            modalTitle.innerText = 'Add New Member';
            setFieldsDisabled(false);
            editToggleBtn.style.display = 'none';
            codeInput.value = nextCode;
            codeInput.readOnly = false;
            modal.style.display = 'block';
        }

        function openEditModal(code) {
            modalTitle.innerText = 'Member Profile: ' + code;
            form.action = 'includes/member_actions.php?action=edit';
            
            // Fetch member data
            fetch(`includes/member_actions?action=get&code=${code}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const m = data.data;
                        document.getElementById('m_Code').value = m.Code;
                        document.getElementById('m_Name').value = m.Name;
                        document.getElementById('m_Father_Name').value = m.Father_Name;
                        document.getElementById('m_Sex').value = m.Sex;
                        document.getElementById('m_VIP').value = m.VIP;
                        document.getElementById('m_Mobile_Number').value = m.Mobile_Number;
                        document.getElementById('m_Email').value = m.Email;
                        document.getElementById('m_Address_1').value = m.Address_1;
                        document.getElementById('m_Address_2').value = m.Address_2;
                        document.getElementById('m_Address_3').value = m.Address_3;
                        document.getElementById('m_Address_4').value = m.Address_4;
                        document.getElementById('m_City').value = m.City;
                        document.getElementById('m_District').value = m.District;
                        document.getElementById('m_State').value = m.State;
                        document.getElementById('m_Country').value = m.Country;
                        document.getElementById('m_PinCode').value = m.PinCode;
                        
                        // Handle multiple checkboxes
                        const selectedYears = (m.Year_Paid || '').split(',').map(y => y.trim());
                        document.querySelectorAll('.year-checkbox').forEach(cb => {
                            cb.checked = selectedYears.includes(cb.value);
                        });

                        document.getElementById('m_Payment_1').value = m.Payment_1;
                        document.getElementById('m_Payment_2').value = m.Payment_2;
                        document.getElementById('m_Payment_3').value = m.Payment_3;
                        
                        setFieldsDisabled(true);
                        editToggleBtn.style.display = 'block';
                        modal.style.display = 'block';
                    } else {
                        alert('Error fetching member details: ' + data.message);
                    }
                });
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        function validateMemberForm() {
            const name = document.getElementById('m_Name').value.trim();
            const mobile = document.getElementById('m_Mobile_Number').value.trim();
            const years = Array.from(document.querySelectorAll('.year-checkbox:checked'));
            const p1 = parseFloat(document.getElementById('m_Payment_1').value) || 0;
            const p2 = parseFloat(document.getElementById('m_Payment_2').value) || 0;
            const p3 = parseFloat(document.getElementById('m_Payment_3').value) || 0;

            if (name === '' && mobile === '' && years.length === 0 && p1 === 0 && p2 === 0 && p3 === 0) {
                alert('Please provide at least one of the following: Name, Mobile Number, or Payment details (Year/Amount).');
                return false;
            }
            return true;
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
            if (event.target == qpModal) {
                closeQuickPaymentModal();
            }
        }
    </script>
</body>
</html>
