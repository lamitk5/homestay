<?php
// Bắt đầu phiên làm việc. Cần thiết để lưu trạng thái đăng nhập.
session_start();

// CẤU HÌNH DATABASE
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "khachsan"; 

$message = "";
$message_type = ""; // success, error

// --------------------------------------------------------
// --- XỬ LÝ ĐĂNG NHẬP KHI FORM ĐƯỢC GỬI ---
// --------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Mở kết nối
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        $message = "Lỗi kết nối database: " . $conn->connect_error;
        $message_type = "error";
    } else {
        $conn->set_charset("utf8mb4");

        // 1. Lấy và làm sạch dữ liệu
        $user_input = trim($_POST['username']);
        $password_input = $_POST['password'];

        // 2. KIỂM TRA TÊN ĐĂNG NHẬP TỒN TẠI
        $stmt_check = $conn->prepare("SELECT makhs, user, matkhau, hoten FROM khachhang WHERE user = ? OR email = ?");
        // Kiểm tra cả trường user và email để người dùng có thể đăng nhập bằng một trong hai
        $stmt_check->bind_param("ss", $user_input, $user_input);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows == 1) {
            $khachhang = $result_check->fetch_assoc();
            $hashed_password = $khachhang['matkhau'];
            
            // 3. XÁC MINH MẬT KHẨU
            // Sử dụng password_verify() để so sánh mật khẩu nhập vào với chuỗi băm
            if (password_verify($password_input, $hashed_password)) {
                
                // Đăng nhập thành công! Lưu thông tin vào SESSION
                $_SESSION['loggedin'] = true;
                $_SESSION['makhs'] = $khachhang['makhs'];
                $_SESSION['user'] = $khachhang['user'];
                $_SESSION['hoten'] = $khachhang['hoten'];
                
                $message = "Đăng nhập thành công! Chào mừng, " . htmlspecialchars($khachhang['hoten']) . ".";
                $message_type = "success";
                
                // Chuyển hướng người dùng đến trang chính (ví dụ: index.php)
                header("Location: index.php?login=success"); 
                exit();
                
            } else {
                $message = "Mật khẩu không chính xác.";
                $message_type = "error";
            }
            
        } else {
            $message = "Tên đăng nhập hoặc email không tồn tại.";
            $message_type = "error";
        }

        $stmt_check->close();
        $conn->close();
    }
    
    if ($message && !$message_type) {
        $message_type = "error";
    }
}
// --------------------------------------------------------
// --- KẾT THÚC XỬ LÝ ĐĂNG NHẬP ---
// --------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập Khách Hàng</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #2980b9;
        }

        .buttonCSS {
            background-color: #f39c12; /* Màu cam nổi bật */
            position: relative;
            border-radius: 10px;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 50px;
            font-weight: bold;
            text-transform: uppercase;
            transition: background-color 0.3s, box-shadow 0.3s;
        }

        .buttonCSS:hover {
            background-color: #e67e22; /* Cam đậm hơn khi hover */
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>

<body class="bg-[#2980b9] min-h-screen flex items-center justify-center p-4">
    <main class="w-full max-w-xl mx-auto">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="md:flex">
                
                <!-- Ảnh bên trái (giữ nguyên để đồng bộ giao diện) -->
                <div class="hidden md:block md:w-1/2 bg-blue-100">
                    <img src="https://placehold.co/600x800/2980b9/ffffff?text=Travel+Login"
                        alt="Login Image"
                        class="w-full h-full object-cover transform hover:scale-105 transition-transform duration-300">
                </div>
                
                <div class="w-full md:w-1/2 p-8">
                    <h2 class="text-3xl font-bold text-center text-blue-800 mb-6">
                        <i class="fas fa-sign-in-alt mr-2"></i>Đăng Nhập Khách Hàng
                    </h2>

                    <!-- PHẦN HIỂN THỊ THÔNG BÁO -->
                    <?php if (!empty($message)): ?>
                        <div id="alertContainer" class="mb-4">
                            <div class="p-4 rounded-lg 
                                <?php echo ($message_type == 'success') ? 'bg-green-100 border-l-4 border-green-500 text-green-700' : 'bg-red-100 border-l-4 border-red-500 text-red-700'; ?>">
                                <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                                <?php echo $message; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form id="loginForm" method="POST" action="login.php" class="space-y-6">
                        
                        <div>
                            <label for="username" class="flex items-center text-sm font-medium text-gray-700 mb-1">Tên đăng nhập hoặc Email</label>
                            <input type="text" name="username" id="username"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                value="<?php echo isset($user_input) ? htmlspecialchars($user_input) : ''; ?>"
                                required>
                        </div>
                        
                        <div>
                            <label for="password" class="flex items-center text-sm font-medium text-gray-700 mb-1">Mật khẩu</label>
                            <input type="password" name="password" id="password"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                required>
                        </div>
                        
                        <button type="submit" class="buttonCSS">
                            <i class="fas fa-lock-open mr-2"></i>Đăng nhập
                        </button>
                    </form>
                    
                    <div class="mt-4 text-center">
                        <p class="text-sm text-gray-600">
                            Chưa có tài khoản? 
                            <a href="register.php" class="text-blue-600 font-semibold hover:text-blue-800 transition">Đăng ký ngay</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>