<?php
// CẤU HÌNH DATABASE
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "khachsan"; // Tên database chính xác
error_reporting(E_ALL); // Bật báo cáo lỗi để dễ debug

// Mở kết nối
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log("Lỗi kết nối database: " . $conn->connect_error);
    $hotels = [];
    $conn = null;
    $db_connected = false;
} else {
    $conn->set_charset("utf8mb4");
    $db_connected = true;
}

// --------------------------------------------------------
// --- PHẦN TRUY VẤN DỮ LIỆU ĐỂ HIỂN THỊ ---
// --------------------------------------------------------

$hotels = [];
if ($db_connected && $conn) {
    // Lấy danh sách khách sạn
    $sql_hotels = "SELECT makhs, tenks, diachi FROM khachsan_info ORDER BY makhs DESC";
    $result_hotels = $conn->query($sql_hotels);
    
    if ($result_hotels && $result_hotels->num_rows > 0) {
        while($row = $result_hotels->fetch_assoc()) {
            $makhs = $row['makhs'];
            
            // Lấy ảnh đại diện (ảnh đầu tiên) từ bảng khachsan_images
            $sql_image = "SELECT image_path FROM khachsan_images WHERE makhs = $makhs ORDER BY image_id ASC LIMIT 1";
            $result_image = $conn->query($sql_image);

            // Gán đường dẫn ảnh hoặc placeholder nếu không có
            $row['HinhAnh'] = ($result_image && $result_image->num_rows > 0) ? $result_image->fetch_assoc()['image_path'] : 'placeholder.png'; 
            $hotels[] = $row;
        }
    }
}

$message = "";
if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
}

if ($db_connected && $conn) {
    $conn->close();
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang Chủ - Tìm Kiếm Khách Sạn</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        body { font-family: 'Inter', sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .header { 
            background-color: #003580; 
            color: white; 
            padding: 20px 0;
            border-bottom: 5px solid #ffb700;
        }
        .main-content { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .hotel-card { 
            background-color: white; 
            border-radius: 12px; 
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); 
            overflow: hidden; 
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .hotel-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        .hotel-image { 
            height: 220px; 
            object-fit: cover; 
            width: 100%; 
        }
        .footer {
            background-color: #002244;
            color: #ccc;
            padding: 40px 0;
        }
        .footer-links a {
            color: #aaa;
            display: block;
            margin-bottom: 8px;
            transition: color 0.2s;
        }
        .footer-links a:hover {
            color: black;
        }
    </style>
</head>
<body>

    <div class="fixed bottom-6 right-6 z-10">
        <a href="manage.php" class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded-full shadow-2xl transition duration-300 transform hover:scale-105 flex items-center">
            <i class="fas fa-tools mr-2"></i> Chức năng Quản Lý (CRUD)
        </a>
    </div>

    <header class="header">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold">Booking.com (Mô Phỏng)</h1>
                <div class="flex space-x-4">
                    <button class="text-white hover:text-yellow-400">VND</button>
                    <button class="text-white hover:text-yellow-400"><i class="fas fa-question-circle"></i> Trợ giúp</button>
                </div>
            </div>
            
            <div class="mt-8 bg-white p-4 rounded-lg shadow-lg border-2 border-yellow-400">
                <div class="flex flex-col md:flex-row space-y-3 md:space-y-0 md:space-x-3">
                    <input type="text" placeholder="Tìm kiếm địa điểm hoặc tên khách sạn..." class="flex-grow p-3 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <input type="date" value="<?= date('Y-m-d') ?>" class="p-3 border border-gray-300 rounded-md">
                    <input type="date" value="<?= date('Y-m-d', strtotime('+2 days')) ?>" class="p-3 border border-gray-300 rounded-md">
                    <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-md transition duration-150">
                        <i class="fas fa-search mr-2"></i> Tìm kiếm
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <?php if (!empty($message)): ?>
            <div class="p-4 mb-6 text-base text-white rounded-lg max-w-7xl mx-auto 
                <?php if (strpos($message, 'Lỗi') !== false) echo 'bg-red-500'; else echo 'bg-green-500'; ?>" role="alert">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <h2 class="text-3xl font-bold text-gray-800 mb-8 border-b pb-3">Các Khách Sạn Nổi Bật</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php if (!$db_connected): ?>
                <div class="col-span-3 bg-red-100 p-8 rounded-xl text-center text-red-700 border border-red-300">
                    <p class="font-bold text-xl mb-3">LỖI KẾT NỐI DATABASE</p>
                    <p>Vui lòng kiểm tra lại thông tin kết nối và tên database (`khachsan`) trong file PHP.</p>
                </div>
            <?php elseif (count($hotels)>0): ?>
                <?php foreach ($hotels as $hotel): ?>
                    <div class="hotel-card">
                        <img src="uploads/<?= htmlspecialchars($hotel['HinhAnh']) ?>" 
                             alt="<?= htmlspecialchars($hotel['tenks']) ?>" 
                             onerror="this.onerror=null; this.src='https://placehold.co/600x400/CCCCCC/333333?text=No+Image'"
                             class="hotel-image">
                        <div class="p-5">
                            <h3 class="text-2xl font-semibold text-blue-800 mb-2"><?= htmlspecialchars($hotel['tenks']) ?></h3>
                            <p class="text-gray-600 mb-4"><i class="fas fa-map-marker-alt mr-2 text-red-500"></i><?= htmlspecialchars($hotel['diachi']) ?></p>
                            
                            <div class="flex justify-between items-center mt-3">
                                <span class="bg-green-100 text-green-800 text-sm font-medium px-2.5 py-0.5 rounded-full">Tuyệt vời (8.5)</span>
                                <a href="hotel_detail.php?makhs=<?= $hotel['makhs'] ?>" 
                                   class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-150 shadow-md flex items-center">
                                    Xem Chi Tiết <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-3 bg-yellow-100 p-8 rounded-xl text-center text-gray-700 border border-yellow-300">
                    <p class="font-bold text-xl mb-3">Chưa có khách sạn nào được hiển thị!</p>
                    <p>Vui lòng chuyển sang <a href="suahome.php" class="text-blue-600 underline font-semibold hover:text-blue-800 transition">Trang Quản Lý</a> để thêm dữ liệu mới.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div class="newsletter-signup-section bg-blue-800 text-white py-12 mt-10">
        <div class="max-w-xl mx-auto text-center">
            <h3 class="text-2xl font-bold mb-2">Tiết kiệm thời gian và tiền bạc!</h3>
            <p class="mb-6">Đăng ký và chúng tôi sẽ gửi những ưu đãi tốt nhất cho bạn.</p>
            <div class="flex justify-center space-x-3">
                <input type="email" placeholder="Địa chỉ email của bạn" class="p-3 rounded-md w-full max-w-sm text-gray-800 border-none">
                <button class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-6 rounded-md transition duration-150">Đăng ký</button>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="max-w-7xl mx-auto px-4">
            <p class="text-center text-xs mt-10 pt-5 border-t border-gray-700">© 2024 Booking.com. Đã đăng ký bản quyền. Giao diện mô phỏng.</p>
        </div>
    </footer>
</body>
</html>

