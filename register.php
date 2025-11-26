<?php
// CẤU HÌNH DATABASE
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "khachsan"; 

$message = "";
$message_type = ""; // success, error

// --------------------------------------------------------
// --- XỬ LÝ ĐĂNG KÝ KHI FORM ĐƯỢC GỬI ---
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
        $hoten = trim($_POST['fullName']);
        $email = trim($_POST['email']);
        $sdt = trim($_POST['phone']);
        $diachi = trim($_POST['address']);
        $user = trim($_POST['username']);
        $matkhau = $_POST['password'];
        $confirmPassword = $_POST['confirmPassword'];
        
        // GIẢ ĐỊNH: Bạn có thể muốn lưu birthdate vào một cột khác
        // Nếu không có cột birthdate trong bảng khachhang, có thể bỏ qua hoặc cần thêm cột.
        // $birthdate = $_POST['birthdate']; 

        // 2. KIỂM TRA HỢP LỆ (Lặp lại validation từ JS để đảm bảo an toàn)
        if (strlen($hoten) < 5) {
            $message = "Họ và tên phải có ít nhất 5 ký tự.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Email không hợp lệ.";
        } elseif ($matkhau !== $confirmPassword) {
            $message = "Mật khẩu và Nhập lại mật khẩu không khớp.";
        } elseif (strlen($matkhau) < 8) {
            $message = "Mật khẩu phải có ít nhất 8 ký tự.";
        } elseif (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $user)) {
             $message = "Tên đăng nhập phải có 4-20 ký tự (chỉ chữ, số, _).";
        } else {
            // 3. KIỂM TRA TRÙNG LẶP (Email và Tên đăng nhập)
            $stmt_check = $conn->prepare("SELECT user, email FROM khachhang WHERE user = ? OR email = ? LIMIT 1");
            $stmt_check->bind_param("ss", $user, $email);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows > 0) {
                $row = $result_check->fetch_assoc();
                if ($row['user'] == $user) {
                    $message = "Tên đăng nhập đã tồn tại. Vui lòng chọn tên khác.";
                } elseif ($row['email'] == $email) {
                    $message = "Email đã được sử dụng. Vui lòng sử dụng email khác.";
                }
            } else {
                // 4. BĂM MẬT KHẨU
                // Sử dụng BCRYPT (thuật toán an toàn nhất hiện nay)
                $hashed_password = password_hash($matkhau, PASSWORD_BCRYPT);

                // 5. THỰC HIỆN CHÈN DỮ LIỆU
                // Lưu ý: Tôi giả định cấu trúc bảng là: hoten, diachi, sdt, email, user, matkhau (chứa hash)
                $sql_insert = "INSERT INTO khachhang (hoten, diachi, sdt, email, user, matkhau) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                
                // s: string, i: integer, d: double
                $stmt_insert->bind_param("ssssss", $hoten, $diachi, $sdt, $email, $user, $hashed_password);

                if ($stmt_insert->execute()) {
                    $new_id = $conn->insert_id;
                    $message = "Đăng ký thành công! Mã Khách Hàng của bạn là **$new_id**. Vui lòng đăng nhập.";
                    $message_type = "success";
                    
                    // Chuyển hướng sau khi đăng ký thành công (Ví dụ đến trang đăng nhập)
                    // header("Location: Dangnhap.html?reg=success"); 
                    // exit();
                } else {
                    $message = "Lỗi khi đăng ký: " . $stmt_insert->error;
                    $message_type = "error";
                }
                $stmt_insert->close();
            }
            $stmt_check->close();
        }

        $conn->close();
    }
    
    // Thiết lập message type cho lỗi nếu chưa được set (từ các khối kiểm tra hợp lệ)
    if ($message && !$message_type) {
        $message_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng kí tài khoản Khách Hàng</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #2980b9;
        }

        .buttonCSS {
            background-color: rgb(31, 107, 164);
            position: relative;
            border-radius: 10px;
            color: rgb(255, 255, 255);
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 50px; /* Tăng chiều cao */
            font-weight: bold;
            text-transform: uppercase;
            transition: background-color 0.3s, box-shadow 0.3s;
        }

        .buttonCSS:hover {
            background-color: rgb(25, 87, 134);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: #ffffff;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #000000;
            width: 80%;
            max-width: 500px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>

<body class="bg-[#2980b9] min-h-screen flex items-center justify-center p-4">
    <main class="w-full max-w-4xl mx-auto">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="md:flex">
                <div class="w-full md:w-1/2 p-8">
                    <h2 class="text-3xl font-bold text-center text-blue-800 mb-6">
                        <i class="fas fa-file-signature mr-2"></i>Đăng Ký Tài Khoản Khách Hàng
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

                    <form id="registerForm" method="POST" action="register.php" class="space-y-6">
                        <!-- Trường này cần method="POST" và action="register_khachhang.php" -->

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="fullName" class="flex items-center text-sm font-medium text-gray-700 mb-1">Họ và tên</label>
                                <input type="text" name="fullName" id="fullName"
                                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    value="<?php echo isset($hoten) ? htmlspecialchars($hoten) : ''; ?>"
                                    required>
                                <div class="invalid-feedback text-red-500 text-sm mt-1 hidden">Vui lòng nhập họ tên hợp
                                    lệ (ít nhất 5 ký tự).</div>
                            </div>
                            <div>
                                <label for="email" class="flex items-center text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" name="email" id="email"
                                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                                    required>
                                <div class="invalid-feedback text-red-500 text-sm mt-1 hidden">Vui lòng nhập email hợp
                                    lệ.</div>
                            </div>
                            <div>
                                <label for="phone" class="flex items-center text-sm font-medium text-gray-700 mb-1">Số điện thoại</label>
                                <input type="tel" name="phone" id="phone" pattern="[0-9]{10,11}"
                                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    value="<?php echo isset($sdt) ? htmlspecialchars($sdt) : ''; ?>"
                                    required>
                                <div class="invalid-feedback text-red-500 text-sm mt-1 hidden">Vui lòng nhập số điện
                                    thoại hợp lệ (10-11 chữ số).</div>
                            </div>
                        </div>
                        <div>
                            <label for="address" class="flex items-center text-sm font-medium text-gray-700 mb-1">Địa chỉ</label>
                            <textarea name="address" id="address" rows="3"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                required><?php echo isset($diachi) ? htmlspecialchars($diachi) : ''; ?></textarea>
                            <div class="invalid-feedback text-red-500 text-sm mt-1 hidden">Vui lòng nhập địa chỉ.</div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="col-span-1 md:col-span-2">
                                <label for="username" class="flex items-center text-sm font-medium text-gray-700 mb-1">Tên đăng nhập</label>
                                <input type="text" name="username" id="username"
                                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    value="<?php echo isset($user) ? htmlspecialchars($user) : ''; ?>"
                                    required>
                                <div class="invalid-feedback text-red-500 text-sm mt-1 hidden">Tên đăng nhập phải có
                                    4-20 ký tự (chữ, số, _).</div>
                            </div>
                            <div>
                                <label for="password" class="flex items-center text-sm font-medium text-gray-700 mb-1">Mật khẩu</label>
                                <input type="password" name="password" id="password"
                                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    required>
                                <div class="invalid-feedback text-red-500 text-sm mt-1 hidden">Mật khẩu phải có ít nhất
                                    8 ký tự.</div>
                            </div>
                            <div>
                                <label for="confirmPassword"
                                    class="flex items-center text-sm font-medium text-gray-700 mb-1">Nhập lại mật khẩu</label>
                                <input type="password" name="confirmPassword" id="confirmPassword"
                                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    required>
                                <div class="invalid-feedback text-red-500 text-sm mt-1 hidden">Mật khẩu nhập lại không
                                    khớp.</div>
                            </div>
                        </div>
                        <button type="submit" class="buttonCSS">
                            <i class="fas fa-user-plus mr-2"></i>Đăng ký ngay
                        </button>
                    </form>
                    <a href="login.php" class="mt-4 inline-block text-blue-600 hover:text-blue-800">
                        Quay lại Trang Chủ
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal và Script JS đã được xóa để chuyển sang PHP/MySQL -->
    <script>
        // JS CLIENT SIDE VALIDATION
        document.getElementById('registerForm').addEventListener('submit', function (e) {
            // Kiểm tra tính nhất quán client-side trước khi gửi
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const confirmPasswordFeedback = confirmPasswordInput.nextElementSibling;
            
            let isValid = true;

            // 1. Kiểm tra mật khẩu có khớp không
            if (password !== confirmPassword) {
                confirmPasswordFeedback.classList.remove('hidden');
                confirmPasswordInput.classList.add('border-red-500');
                isValid = false;
            } else {
                confirmPasswordFeedback.classList.add('hidden');
                confirmPasswordInput.classList.remove('border-red-500');
            }

            // 2. Kiểm tra các trường khác
            const requiredFields = [
                { id: 'fullName', min: 5, msg: 'Họ và tên phải có ít nhất 5 ký tự.' },
                { id: 'email', pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/, msg: 'Vui lòng nhập email hợp lệ.' },
                { id: 'phone', pattern: /^[0-9]{10,11}$/, msg: 'Vui lòng nhập số điện thoại hợp lệ (10-11 chữ số).' },
                { id: 'password', min: 8, msg: 'Mật khẩu phải có ít nhất 8 ký tự.' },
                { id: 'username', pattern: /^[a-zA-Z0-9_]{4,20}$/, msg: 'Tên đăng nhập phải có 4-20 ký tự (chữ, số, _).' },
                { id: 'address', min: 1, msg: 'Vui lòng nhập địa chỉ.' },
                { id: 'birthdate', min: 1, msg: 'Vui lòng chọn ngày sinh.' }
            ];

            requiredFields.forEach(field => {
                const input = document.getElementById(field.id);
                const value = input.value.trim();
                const feedback = input.nextElementSibling;
                let fieldValid = true;

                if (field.min && value.length < field.min) {
                    fieldValid = false;
                } else if (field.pattern && !field.pattern.test(value)) {
                    fieldValid = false;
                } else if (value === '' && input.required) {
                    fieldValid = false;
                }

                if (!fieldValid) {
                    feedback.classList.remove('hidden');
                    input.classList.add('border-red-500');
                    isValid = false;
                } else {
                    feedback.classList.add('hidden');
                    input.classList.remove('border-red-500');
                }
            });

            if (!isValid) {
                e.preventDefault(); // Ngăn form gửi nếu có lỗi JS
            }
        });
    </script>
</body>
</html>