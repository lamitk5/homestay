<?php
// CẤU HÌNH DATABASE
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "khachsan"; 
$upload_dir = "uploads/"; 

// Mở kết nối
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Lỗi kết nối database: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Lấy mã khách sạn từ URL
$makhs = isset($_GET['makhs']) ? intval($_GET['makhs']) : 0;

// Khởi tạo biến dữ liệu
$hotel_info = null;
$hotel_images = [];
$hotel_rooms = [];
$error_message = "";
$success_message = "";

// --------------------------------------------------------
// --- BỔ SUNG: XỬ LÝ ĐẶT PHÒNG (SUBMIT FORM) ---
// --------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'book_room') {
    // Lấy dữ liệu từ Form
    $maphong_book = isset($_POST['maphong']) ? intval($_POST['maphong']) : 0;
    $ngaynhan = isset($_POST['ngaynhan']) ? $_POST['ngaynhan'] : '';
    $ngaytra = isset($_POST['ngaytra']) ? $_POST['ngaytra'] : '';
    
    // Giả định Mã Khách Hàng (makhs) là 1. Bạn cần thay thế bằng logic đăng nhập thực tế.
    // Dựa vào khachsan.sql, cột makhs trong datphong tham chiếu đến khachhang.makhs, 
    // chứ không phải khachsan_info.makhs. Cần làm rõ. TẠM DÙNG makhs = 1 cho KH.
    $makh_dat = 1; 

    // --- BƯỚC 1: KIỂM TRA LỖI LOGIC NGÀY THÁNG ---
    $today = date('Y-m-d');
    if (empty($ngaynhan) || empty($ngaytra) || $maphong_book <= 0) {
        $error_message = "Vui lòng chọn đầy đủ ngày nhận/trả phòng và Mã phòng.";
    } elseif ($ngaynhan < $today) {
        $error_message = "Ngày nhận phòng không thể là ngày trong quá khứ.";
    } elseif ($ngaytra <= $ngaynhan) {
        $error_message = "Ngày trả phòng phải sau ngày nhận phòng.";
    } else {
        // --- BƯỚC 2: KIỂM TRA TÍNH SẴN SÀNG CỦA PHÒNG ---
        // Tìm bất kỳ bản ghi đặt phòng nào khác trùng lặp thời gian
        $sql_check = "SELECT madp FROM datphong 
                      WHERE maphong = ? 
                      AND trangthai_dat IN ('Đã xác nhận', 'Chờ xác nhận') 
                      AND (
                          (ngaynhan <= ? AND ngaytra > ?) OR   -- Đặt phòng bắt đầu trước và kết thúc trong khoảng
                          (ngaynhan < ? AND ngaytra >= ?) OR   -- Đặt phòng bắt đầu trong và kết thúc sau khoảng
                          (ngaynhan >= ? AND ngaytra <= ?)     -- Đặt phòng nằm hoàn toàn trong khoảng
                      )
                      LIMIT 1";
        
        $stmt_check = $conn->prepare($sql_check);
        if ($stmt_check) {
            $stmt_check->bind_param("issssss", 
                $maphong_book, 
                $ngaynhan, $ngaynhan, 
                $ngaytra, $ngaytra,
                $ngaynhan, $ngaytra
            );
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows > 0) {
                // Phòng đã bị đặt trong khoảng thời gian này
                $error_message = "Đặt phòng thất bại! Phòng này đã có người đặt trong khoảng thời gian từ **{$ngaynhan}** đến **{$ngaytra}**.";
            } else {
                // --- BƯỚC 3: TIẾN HÀNH LƯU ĐẶT PHÒNG ---
                $sql_insert = "INSERT INTO datphong (makhs, maphong, ngaynhan, ngaytra, trangthai_dat) 
                               VALUES (?, ?, ?, ?, 'Chờ xác nhận')";
                $stmt_insert = $conn->prepare($sql_insert);
                
                if ($stmt_insert) {
                    // makhs ở đây là Mã Khách Hàng (Giả định là 1)
                    $stmt_insert->bind_param("iiss", $makh_dat, $maphong_book, $ngaynhan, $ngaytra); 
                    
                    if ($stmt_insert->execute()) {
                        $success_message = "Đặt phòng thành công! Chúng tôi sẽ liên hệ để xác nhận đơn hàng của bạn (Mã DP: {$conn->insert_id}).";
                        // Chuyển hướng người dùng để xóa biến POST
                        header("Location: hotel_detail.php?makhs={$makhs}&msg=" . urlencode($success_message));
                        exit();
                    } else {
                        $error_message = "Lỗi khi lưu đặt phòng vào database: " . $stmt_insert->error;
                    }
                    $stmt_insert->close();
                } else {
                    $error_message = "Lỗi chuẩn bị truy vấn đặt phòng: " . $conn->error;
                }
            }
            $stmt_check->close();
        } else {
            $error_message = "Lỗi chuẩn bị truy vấn kiểm tra phòng: " . $conn->error;
        }
    }
}
// --------------------------------------------------------
// --- KẾT THÚC XỬ LÝ ĐẶT PHÒNG ---
// --------------------------------------------------------


// --- BẮT ĐẦU TRUY VẤN DỮ LIỆU KHÁCH SẠN (Giữ nguyên) ---
if ($makhs > 0) {
    $stmt_info = $conn->prepare("SELECT tenks, diachi, mo_ta_chi_tiet FROM khachsan_info WHERE makhs = ?");
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

    if ($hotel_info) {
        // LẤY HÌNH ẢNH
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
        
        // LẤY THÔNG TIN PHÒNG
        $sql_rooms = "SELECT maphong, sophong, loaiphong, giaphong, trangthai 
                      FROM phong 
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
            $error_message .= "<br>Lưu ý: Không thể lấy dữ liệu phòng. Vui lòng đảm bảo bảng 'phong' có cột 'makhs' để liên kết.";
        }
    }
} else {
    $error_message = "Mã khách sạn không hợp lệ.";
}

$conn->close();

// Lấy thông báo từ URL sau khi đặt phòng thành công
if (isset($_GET['msg'])) {
    $success_message = htmlspecialchars($_GET['msg']);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiết Khách Sạn: <?= $hotel_info ? htmlspecialchars($hotel_info['tenks']) : 'Đang tải...' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
    /* 1. Global & Typography */
    body { 
        font-family: 'Inter', sans-serif; 
        background-color: #f8f9fa; /* Nền sáng, ấm hơn */
        color: #343a40; /* Màu chữ chính */
    }
    
    /* 2. Image Gallery Styling */
    .gallery-container {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(4, 1fr); 
        grid-template-rows: repeat(2, 1fr); 
        height: 480px;
        overflow: hidden;
        border-radius: 16px;
    }

    .gallery-container > div {
        overflow: hidden;
        position: relative;
        border-radius: 8px;
    }

    .gallery-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.4s ease-in-out; 
    }

    .gallery-container img:hover {
        transform: scale(1.08); 
    }

    /* Quy tắc đặc biệt cho ảnh lớn đầu tiên */
    .gallery-container div:first-child {
        grid-column: 1 / 3;
        grid-row: 1 / 3;
    }
    
    /* Hiệu ứng lớp phủ cho "x ảnh nữa" */
    .more-images-overlay {
        transition: background-color 0.3s ease;
    }
    .more-images-overlay:hover {
        background-color: rgba(0, 0, 0, 0.75); /* Tối hơn khi hover */
    }

    /* 3. Responsive Design for Gallery */
    @media (max-width: 768px) {
        .gallery-container {
            grid-template-columns: 1fr;
            grid-template-rows: auto;
            height: auto;
            gap: 8px;
        }
        .gallery-container div:first-child {
            grid-column: auto;
            grid-row: auto;
        }
        .gallery-container img {
            height: 250px;
        }
    }
    
    /* Custom style cho phần thông báo lỗi/chú ý */
    .alert-box {
        padding: 1rem 1.5rem;
        border-radius: 0.75rem;
        margin-bottom: 1.5rem;
    }
</style>
</head>
<body>
    <div class="fixed bottom-6 right-6 z-10">
        <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-full shadow-2xl transition duration-300 transform hover:scale-105 flex items-center">
            <i class="fas fa-home mr-2"></i> Trở về Trang Chủ
        </a>
    </div>

    <div class="container mx-auto mt-8 mb-16 px-4 md:px-0">
        <!-- HIỂN THỊ THÔNG BÁO LỖI -->
        <?php if (!empty($error_message)): ?>
            <div class="alert-box bg-red-100 text-red-700 border border-red-300 shadow-lg" role="alert">
                <i class="fas fa-exclamation-triangle mr-2"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <!-- HIỂN THỊ THÔNG BÁO THÀNH CÔNG -->
        <?php if (!empty($success_message)): ?>
            <div class="alert-box bg-green-100 text-green-700 border border-green-300 shadow-lg" role="alert">
                <i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($hotel_info): ?>
            <header class="mb-10">
                <h1 class="text-5xl font-extrabold text-gray-900 mb-2">
                    <i class="fas fa-crown text-yellow-500 mr-3"></i> 
                    <?= htmlspecialchars($hotel_info['tenks']) ?>
                </h1>
                <p class="text-xl text-gray-600 mb-4">
                    <i class="fas fa-map-marker-alt mr-2 text-red-500"></i>
                    <?= htmlspecialchars($hotel_info['diachi']) ?>
                </p>
                <span class="bg-blue-600 text-white text-sm font-semibold px-4 py-1.5 rounded-full inline-block shadow-lg">
                    Mã KS: <?= $makhs ?>
                </span>
            </header>
            
            <!-- FORM CHỌN NGÀY THÁNG ĐẶT PHÒNG -->
            <div class="bg-white shadow-xl rounded-2xl p-6 mb-10 border-t-4 border-blue-500">
                <h2 class="text-2xl font-bold text-gray-800 mb-4"><i class="fas fa-calendar-alt mr-2 text-blue-500"></i> Chọn Ngày Nhận/Trả Phòng</h2>
                <form id="date-selection-form" method="GET" action="hotel_detail.php" class="flex flex-col md:flex-row gap-4 items-end">
                    <input type="hidden" name="makhs" value="<?= $makhs ?>">
                    
                    <div class="flex-1 w-full">
                        <label for="ngaynhan" class="block text-sm font-medium text-gray-700 mb-1">Ngày Nhận Phòng</label>
                        <input type="date" name="ngaynhan" id="ngaynhan" 
                               value="<?= isset($_GET['ngaynhan']) ? htmlspecialchars($_GET['ngaynhan']) : date('Y-m-d') ?>" 
                               min="<?= date('Y-m-d') ?>"
                               required class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    
                    <div class="flex-1 w-full">
                        <label for="ngaytra" class="block text-sm font-medium text-gray-700 mb-1">Ngày Trả Phòng</label>
                        <input type="date" name="ngaytra" id="ngaytra" 
                               value="<?= isset($_GET['ngaytra']) ? htmlspecialchars($_GET['ngaytra']) : date('Y-m-d', strtotime('+1 day')) ?>" 
                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                               required class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    
                    <div class="flex-shrink-0 w-full md:w-auto">
                        <button type="submit" class="w-full md:w-auto bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-3 px-6 rounded-lg transition duration-150 shadow-md">
                            <i class="fas fa-sync-alt mr-2"></i> Kiểm Tra Sẵn Sàng
                        </button>
                    </div>
                </form>
                <?php if (isset($_GET['ngaynhan']) && isset($_GET['ngaytra'])): ?>
                    <p class="text-sm mt-3 text-green-600 font-semibold">
                        Kết quả hiển thị bên dưới dựa trên lịch trình: 
                        <span class="text-indigo-700"><?= htmlspecialchars($_GET['ngaynhan']) ?></span> đến 
                        <span class="text-indigo-700"><?= htmlspecialchars($_GET['ngaytra']) ?></span>.
                    </p>
                <?php else: ?>
                    <p class="text-sm mt-3 text-gray-500">Vui lòng chọn ngày để kiểm tra phòng trống chính xác hơn.</p>
                <?php endif; ?>
            </div>

            <!-- THƯ VIỆN ẢNH (Giữ nguyên) -->
            <div class="mb-12">
                <h2 class="text-3xl font-bold text-gray-800 mb-5"><i class="fas fa-images mr-2 text-teal-500"></i> Thư viện ảnh (<?= count($hotel_images) ?>)</h2>
                <?php if (count($hotel_images) > 0): ?>
                    <div class="gallery-container shadow-xl">
                        <?php 
                        $displayed_images = array_slice($hotel_images, 0, 5);
                        foreach ($displayed_images as $index => $path): 
                        ?>
                            <div class="relative">
                                <img src="<?= $upload_dir . htmlspecialchars($path) ?>" 
                                    alt="Ảnh Khách Sạn <?= $index + 1 ?>"
                                    class="p-0"
                                    onerror="this.onerror=null; this.src='https://placehold.co/600x400/CCCCCC/333333?text=Lỗi+Ảnh'; this.classList.add('p-4')">
                                
                                <?php if ($index == 4 && count($hotel_images) > 5): ?>
                                    <div class="more-images-overlay absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center text-white text-2xl font-bold cursor-pointer">
                                        +<?= count($hotel_images) - 5 ?> ảnh nữa
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-8 bg-gray-200 rounded-xl text-center text-gray-600 shadow-inner">
                        <i class="fas fa-camera-retro mr-2 text-2xl"></i> Khách sạn này chưa có hình ảnh nào được tải lên.
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
                <div class="lg:col-span-2">
                    <div class="bg-white shadow-xl rounded-2xl p-8 mb-10">
                        <h2 class="text-3xl font-bold text-gray-800 mb-5 border-b pb-2"><i class="fas fa-info-circle mr-2 text-blue-500"></i> Mô Tả Chi Tiết</h2>
                        <div class="text-gray-700 leading-relaxed whitespace-pre-wrap">
                            <?= nl2br(htmlspecialchars($hotel_info['mo_ta_chi_tiet'])) ?>
                        </div>
                    </div>

                    <!-- DANH SÁCH PHÒNG CÓ FORM ĐẶT PHÒNG NHỎ -->
                    <div class="bg-white shadow-xl rounded-2xl p-8">
                        <h2 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2"><i class="fas fa-bed mr-2 text-indigo-500"></i> Danh Sách Các Loại Phòng</h2>
                        
                        <?php if (count($hotel_rooms) > 0): ?>
                            <div class="space-y-6">
                                <?php 
                                $ngaynhan_check = isset($_GET['ngaynhan']) ? $_GET['ngaynhan'] : null;
                                $ngaytra_check = isset($_GET['ngaytra']) ? $_GET['ngaytra'] : null;
                                
                                foreach ($hotel_rooms as $room): 
                                    $is_available = ($room['trangthai'] == 1);
                                    $availability_status = $is_available ? 'Có sẵn' : 'Không hoạt động';

                                    // Nếu có chọn ngày, kiểm tra tính sẵn sàng trong khoảng thời gian
                                    if ($ngaynhan_check && $ngaytra_check) {
                                        // Mở lại kết nối để kiểm tra (chỉ nên làm điều này một lần cho tất cả phòng nếu code tối ưu)
                                        $conn_check = new mysqli($servername, $username, $password, $dbname);
                                        $conn_check->set_charset("utf8mb4");

                                        $sql_check = "SELECT madp FROM datphong 
                                                      WHERE maphong = ? 
                                                      AND trangthai_dat IN ('Đã xác nhận', 'Chờ xác nhận') 
                                                      AND (
                                                          (ngaynhan <= ? AND ngaytra > ?) OR 
                                                          (ngaynhan < ? AND ngaytra >= ?) OR   
                                                          (ngaynhan >= ? AND ngaytra <= ?)     
                                                      )
                                                      LIMIT 1";
                                        
                                        $stmt_check_room = $conn_check->prepare($sql_check);
                                        $stmt_check_room->bind_param("issssss", 
                                            $room['maphong'], 
                                            $ngaynhan_check, $ngaynhan_check, 
                                            $ngaytra_check, $ngaytra_check,
                                            $ngaynhan_check, $ngaytra_check
                                        );
                                        $stmt_check_room->execute();
                                        $result_check_room = $stmt_check_room->get_result();
                                        
                                        if ($result_check_room->num_rows > 0) {
                                            $is_available = false;
                                            $availability_status = 'Đã đặt (Trùng lịch)';
                                        } else {
                                            // Nếu phòng có trạng thái 1 (Sẵn sàng) và không trùng lịch
                                            $is_available = ($room['trangthai'] == 1);
                                            $availability_status = $is_available ? 'SẴN SÀNG ĐẶT' : 'Không hoạt động';
                                        }

                                        $stmt_check_room->close();
                                        $conn_check->close();
                                    }
                                    
                                    // Xác định màu sắc trạng thái
                                    $status_class = '';
                                    if ($is_available) {
                                        $status_class = 'bg-green-200 text-green-800 border-green-500';
                                    } elseif ($room['trangthai'] == 0) {
                                        $status_class = 'bg-red-200 text-red-800 border-red-500';
                                    } else {
                                        $status_class = 'bg-yellow-200 text-yellow-800 border-yellow-500';
                                    }

                                    $btn_disabled = !$is_available || !$ngaynhan_check || !$ngaytra_check;
                                ?>
                                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between p-5 rounded-xl border-l-4 <?= $status_class ?> shadow-md hover:shadow-lg transition duration-200">
                                        
                                        <div class="flex-1 mb-3 md:mb-0">
                                            <p class="text-xl font-bold text-indigo-800">Loại Phòng: <?= htmlspecialchars($room['loaiphong']) ?></p>
                                            <p class="text-md text-gray-700 font-semibold mt-1">Số phòng trống: <?= htmlspecialchars($room['sophong']) ?></p>
                                            <p class="text-sm text-gray-500">Mã Phòng: <?= htmlspecialchars($room['maphong']) ?></p>
                                            
                                            <p class="text-sm mt-2">
                                                Trạng thái: 
                                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full 
                                                    <?= $status_class ?>">
                                                    <?= $availability_status ?>
                                                </span>
                                            </p>
                                        </div>

                                        <div class="text-right flex-shrink-0">
                                            <p class="text-3xl font-extrabold text-green-600">
                                                <?= number_format($room['giaphong'], 0, ',', '.') ?> VNĐ
                                            </p>
                                            <p class="text-sm text-gray-500 mb-2">/ đêm</p>
                                            
                                            <!-- FORM ĐẶT PHÒNG CHO TỪNG PHÒNG -->
                                            <form method="POST" action="hotel_detail.php?makhs=<?= $makhs ?>" class="inline-block">
                                                <input type="hidden" name="action" value="book_room">
                                                <input type="hidden" name="maphong" value="<?= htmlspecialchars($room['maphong']) ?>">
                                                <input type="hidden" name="ngaynhan" value="<?= htmlspecialchars($ngaynhan_check) ?>">
                                                <input type="hidden" name="ngaytra" value="<?= htmlspecialchars($ngaytra_check) ?>">
                                                
                                                <button type="submit"
                                                    class="bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-5 rounded-lg transition duration-150 shadow-md 
                                                    <?= $btn_disabled ? 'opacity-50 cursor-not-allowed' : '' ?>"
                                                    <?= $btn_disabled ? 'disabled' : '' ?>
                                                    onclick="return confirm('Bạn có chắc chắn muốn đặt phòng Mã <?= htmlspecialchars($room['maphong']) ?> từ <?= htmlspecialchars($ngaynhan_check) ?> đến <?= htmlspecialchars($ngaytra_check) ?>?');"
                                                >
                                                    Đặt Ngay <i class="fas fa-chevron-right ml-1"></i>
                                                </button>
                                            </form>
                                            <?php if ($btn_disabled && !$is_available): ?>
                                                <p class="text-xs text-red-500 mt-1">Không thể đặt phòng này.</p>
                                            <?php elseif ($btn_disabled && !$ngaynhan_check): ?>
                                                <p class="text-xs text-red-500 mt-1">Vui lòng chọn ngày trước.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="p-6 bg-yellow-100 rounded-lg text-center text-gray-700 border border-yellow-300">
                                <i class="fas fa-info-circle mr-2"></i> Khách sạn này hiện chưa có phòng nào được liệt kê.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="lg:col-span-1">
                    <div class="bg-white shadow-xl rounded-2xl p-8 sticky top-8">
                        <h3 class="text-2xl font-bold text-gray-800 mb-4"><i class="fas fa-star mr-2 text-red-500"></i> Điểm nổi bật</h3>
                        <ul class="space-y-3 text-gray-700">
                            <li class="flex items-center"><i class="fas fa-wifi text-blue-500 mr-3"></i> Wifi tốc độ cao miễn phí</li>
                            <li class="flex items-center"><i class="fas fa-coffee text-amber-600 mr-3"></i> Bữa sáng miễn phí</li>
                            <li class="flex items-center"><i class="fas fa-parking text-green-500 mr-3"></i> Bãi đỗ xe riêng</li>
                            <li class="flex items-center"><i class="fas fa-swimming-pool text-cyan-500 mr-3"></i> Hồ bơi bốn mùa</li>
                        </ul>
                        
                        <div class="mt-6 border-t pt-4">
                            <h3 class="text-2xl font-bold text-gray-800 mb-3"><i class="fas fa-clock mr-2 text-purple-500"></i> Quy tắc</h3>
                            <p class="text-sm text-gray-600">
                                Giờ nhận phòng: **14:00**<br>
                                Giờ trả phòng: **12:00**
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>

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