<?php
// CẤU HÌNH DATABASE
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "khachsan"; 
$upload_dir = "uploads/";

// Lấy mã khách sạn từ URL
$makhs = isset($_GET['makhs']) ? intval($_GET['makhs']) : 0;

// Khởi tạo biến dữ liệu
$hotel_info = null;
$hotel_images = [];
$hotel_rooms = [];
$error_message = "";

// Mở kết nối
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    $error_message = "Lỗi kết nối database: " . $conn->connect_error;
} else {
    $conn->set_charset("utf8mb4");

    if ($makhs > 0) {
        // --------------------------------------------------------
        // 1. LẤY THÔNG TIN KHÁCH SẠN (khachsan_info)
        // --------------------------------------------------------
        $stmt_info = $conn->prepare("SELECT tenks, diachi FROM khachsan_info WHERE makhs = ?");
        if ($stmt_info) {
            $stmt_info->bind_param("i", $makhs);
            $stmt_info->execute();
            $result_info = $stmt_info->get_result();
            if ($result_info->num_rows == 1) {
                $hotel_info = $result_info->fetch_assoc();
            } else {
                $error_message = "Không tìm thấy khách sạn với Mã: " . $makhs;
            }
            $stmt_info->close();
        } else {
            $error_message = "Lỗi chuẩn bị truy vấn thông tin khách sạn: " . $conn->error;
        }

        // Chỉ tiếp tục nếu tìm thấy khách sạn
        if ($hotel_info) {
            
            // --------------------------------------------------------
            // 2. LẤY HÌNH ẢNH (khachsan_images)
            // --------------------------------------------------------
            $stmt_images = $conn->prepare("SELECT image_path FROM khachsan_images WHERE makhs = ? ORDER BY image_id ASC");
            if ($stmt_images) {
                $stmt_images->bind_param("i", $makhs);
                $stmt_images->execute();
                $result_images = $stmt_images->get_result();
                while ($row = $result_images->fetch_assoc()) {
                    $hotel_images[] = $row['image_path'];
                }
                $stmt_images->close();
            }
            
            // --------------------------------------------------------
            // 3. LẤY THÔNG TIN PHÒNG (phong)
            // LƯU Ý: Giả định bảng 'phong' có cột 'makhs' để liên kết với khách sạn. 
            // Nếu không có, bạn cần điều chỉnh CSDL hoặc query này.
            // --------------------------------------------------------
            $sql_rooms = "SELECT maphong, tenphong, loaiphong, giaphong 
                          FROM phong 
                          -- ĐÃ GIẢ ĐỊNH CỘT makhs TỒN TẠI TRONG BẢNG PHONG
                          WHERE makhs = ? 
                          ORDER BY giaphong ASC";
            $stmt_rooms = $conn->prepare($sql_rooms);

            if ($stmt_rooms) {
                $stmt_rooms->bind_param("i", $makhs);
                $stmt_rooms->execute();
                $result_rooms = $stmt_rooms->get_result();
                while ($row = $result_rooms->fetch_assoc()) {
                    $hotel_rooms[] = $row;
                }
                $stmt_rooms->close();
            } else {
                // Nếu query lỗi (do cột makhs không tồn tại trong bảng phong)
                $error_message .= "<br>Lưu ý: Không thể lấy dữ liệu phòng. Vui lòng đảm bảo bảng 'phong' có cột 'makhs' để liên kết.";
            }
        }
    } else {
        $error_message = "Mã khách sạn không hợp lệ.";
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiết Khách Sạn: <?= $hotel_info ? htmlspecialchars($hotel_info['tenks']) : 'Đang tải...' ?></title>
    <!-- Tải Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f9; }
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .card { background-color: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); padding: 24px; }
        .gallery-container {
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(4, 1fr); /* Mặc định 4 cột */
            grid-template-rows: repeat(2, 1fr); /* Mặc định 2 hàng */
            height: 450px; /* Chiều cao cố định cho gallery */
            overflow: hidden;
            border-radius: 12px;
        }
        .gallery-container > div {
            overflow: hidden;
            position: relative;
        }
        .gallery-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .gallery-container img:hover {
            transform: scale(1.05);
        }
        .gallery-container div:first-child {
            grid-column: 1 / 3; /* Ảnh đầu tiên chiếm 2 cột */
            grid-row: 1 / 3; /* Ảnh đầu tiên chiếm 2 hàng */
        }
        @media (max-width: 768px) {
            .gallery-container {
                grid-template-columns: 1fr;
                grid-template-rows: auto;
                height: auto;
            }
            .gallery-container div:first-child {
                grid-column: auto;
                grid-row: auto;
            }
            .gallery-container img {
                height: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="fixed bottom-6 right-6 z-10">
        <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-full shadow-2xl transition duration-300 transform hover:scale-105 flex items-center">
            <i class="fas fa-home mr-2"></i> Trở về Trang Chủ
        </a>
    </div>

    <div class="container">
        <!-- PHẦN THÔNG BÁO LỖI -->
        <?php if (!empty($error_message)): ?>
            <div class="card bg-red-100 text-red-700 border border-red-300 mb-6" role="alert">
                <i class="fas fa-exclamation-triangle mr-2"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- PHẦN THÔNG TIN CHI TIẾT -->
        <?php if ($hotel_info): ?>
            <header class="mb-8">
                <h1 class="text-5xl font-extrabold text-gray-900 mb-2">
                    <i class="fas fa-crown text-yellow-500 mr-3"></i> 
                    <?= htmlspecialchars($hotel_info['tenks']) ?>
                </h1>
                <p class="text-xl text-gray-600 mb-4">
                    <i class="fas fa-map-marker-alt mr-2 text-red-500"></i>
                    <?= htmlspecialchars($hotel_info['diachi']) ?>
                </p>
                <span class="bg-blue-600 text-white text-sm font-semibold px-4 py-1.5 rounded-full inline-block shadow-md">
                    Mã KS: <?= $makhs ?>
                </span>
            </header>

            <!-- GALLERY ẢNH -->
            <div class="mb-10">
                <h2 class="text-3xl font-bold text-gray-800 mb-4"><i class="fas fa-images mr-2 text-teal-500"></i> Thư viện ảnh (<?= count($hotel_images) ?>)</h2>
                <?php if (count($hotel_images) > 0): ?>
                    <div class="gallery-container">
                        <?php 
                        // Hiển thị tối đa 5 ảnh theo layout grid
                        $displayed_images = array_slice($hotel_images, 0, 5);
                        foreach ($displayed_images as $index => $path): 
                        ?>
                            <div class="relative">
                                <img src="<?= $upload_dir . htmlspecialchars($path) ?>" 
                                     alt="Ảnh Khách Sạn <?= $index + 1 ?>"
                                     onerror="this.onerror=null; this.src='https://placehold.co/600x400/CCCCCC/333333?text=Lỗi+Ảnh'; this.classList.add('p-4')">
                                <?php if ($index == 4 && count($hotel_images) > 5): ?>
                                    <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center text-white text-2xl font-bold cursor-pointer">
                                        +<?= count($hotel_images) - 5 ?> ảnh nữa
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($hotel_images) == 0): ?>
                        <div class="p-6 bg-gray-200 rounded-lg text-center text-gray-600">
                             <i class="fas fa-camera-retro mr-2"></i> Khách sạn này chưa có hình ảnh nào được tải lên.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="p-6 bg-gray-200 rounded-lg text-center text-gray-600">
                         <i class="fas fa-camera-retro mr-2"></i> Khách sạn này chưa có hình ảnh nào được tải lên.
                    </div>
                <?php endif; ?>
            </div>

            <!-- PHẦN DANH SÁCH PHÒNG -->
            <div class="card">
                <h2 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2"><i class="fas fa-bed mr-2 text-indigo-500"></i> Danh Sách Các Loại Phòng</h2>
                
                <?php if (count($hotel_rooms) > 0): ?>
                    <div class="space-y-6">
                        <?php foreach ($hotel_rooms as $room): ?>
                            <div class="flex flex-col md:flex-row items-start md:items-center justify-between p-4 bg-indigo-50 rounded-xl border border-indigo-200 hover:shadow-lg transition duration-200">
                                <div class="flex-1 mb-2 md:mb-0">
                                    <p class="text-lg font-bold text-indigo-800"><?= htmlspecialchars($room['tenphong']) ?></p>
                                    <p class="text-sm text-gray-600">Mã Phòng: <?= htmlspecialchars($room['maphong']) ?> | Loại: <?= htmlspecialchars($room['loaiphong']) ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-extrabold text-green-600">
                                        <?= number_format($room['giaphong'], 0, ',', '.') ?> VNĐ
                                    </p>
                                    <p class="text-xs text-gray-500">/ đêm</p>
                                    <button class="mt-2 bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-150 shadow-md">
                                        Đặt Ngay <i class="fas fa-chevron-right ml-1"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-6 bg-yellow-100 rounded-lg text-center text-gray-700 border border-yellow-300">
                        <i class="fas fa-info-circle mr-2"></i> Khách sạn này hiện chưa có phòng nào được liệt kê hoặc thông tin phòng chưa được liên kết chính xác.
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>

        <!-- Nút quay lại chỉ hiện khi có lỗi hoặc thông tin KS không hợp lệ -->
        <?php if (!$hotel_info && !empty($error_message)): ?>
            <div class="text-center mt-8">
                <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-150 transform hover:scale-105">
                    <i class="fas fa-arrow-left mr-2"></i> Quay lại Trang Chủ
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>