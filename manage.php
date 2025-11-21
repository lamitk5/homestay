<?php
// Đây là file manage_data.php đã được chỉnh sửa để hỗ trợ:
// 1. Upload nhiều hình ảnh cho Khách Sạn.
// 2. Thêm và hiển thị cột 'mo_ta_chi_tiet' (dạng LONGTEXT).
// 3. BỔ SUNG LOGIC RESET AUTO_INCREMENT KHI XÓA KHÁCH SẠN.
// 4. BỔ SUNG LOGIC XÓA ẢNH ĐƠN LẺ TRONG PHẦN SỬA KHÁCH SẠN.
/**
 * Hàm Reset AUTO_INCREMENT của bảng về giá trị max_id + 1, hoặc về 1 nếu bảng trống.
 * @param mysqli $conn Đối tượng kết nối CSDL.
 * @param string $table_name Tên bảng cần reset.
 * @param string $id_column Tên cột ID (PRIMARY KEY) của bảng.
 * @return string Thông báo kết quả reset.
 */
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "khachsan";
$upload_dir = "uploads/"; // Định nghĩa thư mục upload

// Mở kết nối
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Lỗi kết nối: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Hàm làm sạch dữ liệu đầu vào
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}

// Hàm làm sạch dữ liệu đầu vào cho trường TEXT AREA
function clean_textarea($data) {
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}

// Hàm Reset AUTO_INCREMENT (Đã giữ lại từ yêu cầu trước)
function reset_auto_increment($conn, $table_name, $id_column) {
    $sql_max = "SELECT MAX({$id_column}) AS max_id FROM {$table_name}";
    $result_max = $conn->query($sql_max);
    $row_max = $result_max->fetch_assoc();
    $max_id = (int)$row_max['max_id'];
    $next_auto_increment = $max_id + 1;
    if ($max_id === 0) {
        $next_auto_increment = 1;
    }
    
    $sql_reset = "ALTER TABLE {$table_name} AUTO_INCREMENT = {$next_auto_increment}";
    if ($conn->query($sql_reset)) {
        return "Tự động tăng của bảng **{$table_name}** đã được đặt lại về **{$next_auto_increment}**.";
    } else {
        return "Lỗi đặt lại AUTO_INCREMENT cho {$table_name}: " . $conn->error;
    }
}

$message = "";

// --- HÀM XỬ LÝ UPLOAD NHIỀU FILE VÀ LƯU VÀO CSDL ---
function handle_multiple_uploads($conn, $makhs, &$message) {
    global $upload_dir; // Sử dụng biến toàn cục
    if (isset($_FILES['HinhAnh']) && is_array($_FILES['HinhAnh']['name'])) {
        $file_count = count($_FILES['HinhAnh']['name']);
        $uploaded_count = 0;
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES["HinhAnh"]["error"][$i] == 0 && !empty($_FILES["HinhAnh"]["name"][$i])) {
                $file_name = basename($_FILES["HinhAnh"]["name"][$i]);
                $temp_name = $_FILES["HinhAnh"]["tmp_name"][$i];
                $new_file_name = time() . '_' . $makhs . '_' . $i . '_' . $file_name; 
                $target_file = $upload_dir . $new_file_name;

                if (move_uploaded_file($temp_name, $target_file)) {
                    $sql_img = "INSERT INTO khachsan_images (makhs, image_path) VALUES (?, ?)";
                    $stmt_img = $conn->prepare($sql_img);
                    $stmt_img->bind_param("is", $makhs, $new_file_name); 
                    
                    if ($stmt_img->execute()) {
                         $uploaded_count++;
                    } else {
                        $message .= "Lỗi lưu file vào CSDL: " . $stmt_img->error;
                    }
                    $stmt_img->close();
                } else {
                    $message .= "Lỗi upload file: " . $file_name . ". ";
                }
            }
        }
        if ($uploaded_count > 0) {
             $message .= " Đã thêm thành công {$uploaded_count} hình ảnh mới.";
        }
    }
}
// --------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    
    // Khởi tạo các biến chung
    $id = isset($_POST['MaKhachSan']) ? (int)$_POST['MaKhachSan'] : null;
    $action = $_POST['action'];

    // --------------------------------------------------------
    // --- Xử lý THÊM / SỬA Khách Sạn (khachsan_info) ---
    // --------------------------------------------------------
    if (in_array($action, ['add', 'edit'])) {
        $tenks = clean_input($_POST['TenKhachSan']);
        $diachi = clean_input($_POST['DiaDiem']); 
        $mo_ta = clean_textarea($_POST['MoTaChiTiet']); 
        
        if ($action == 'add') {
            // --- THÊM KHÁCH SẠN ---
            $sql_add = "INSERT INTO khachsan_info (tenks, diachi, mo_ta_chi_tiet) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql_add);
            $stmt->bind_param("sss", $tenks, $diachi, $mo_ta); 

            if ($stmt->execute()) {
                $new_id = $conn->insert_id; 
                $message .= "Thêm khách sạn thành công!";
                handle_multiple_uploads($conn, $new_id, $message); 
            } else {
                $message .= "Lỗi thêm khách sạn: " . $stmt->error;
            }
            $stmt->close();

        } elseif ($action == 'edit' && $id) {
            // --- SỬA KHÁCH SẠN ---
            $sql_update = "UPDATE khachsan_info SET tenks=?, diachi=?, mo_ta_chi_tiet=? WHERE makhs=?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("sssi", $tenks, $diachi, $mo_ta, $id); 

            if ($stmt->execute()) {
                $message .= "Cập nhật thông tin khách sạn thành công!";
                handle_multiple_uploads($conn, $id, $message); 
            } else {
                $message .= "Lỗi cập nhật khách sạn: " . $stmt->error;
            }
            $stmt->close();
        }
    
    } 
// --------------------------------------------------------
    // --- Xử lý XÓA ẢNH ĐƠN LẺ (RESET image_id) ---
    // --------------------------------------------------------
    elseif ($action == 'delete_image' && isset($_POST['image_id']) && isset($_POST['makhs'])) {
        $image_id = (int)$_POST['image_id'];
        $makhs_id = (int)$_POST['makhs'];
        $deleted = false;

        // 1. Lấy đường dẫn file từ CSDL
        $sql_get_path = "SELECT image_path FROM khachsan_images WHERE image_id = ?";
        $stmt_path = $conn->prepare($sql_get_path);
        $stmt_path->bind_param("i", $image_id);
        $stmt_path->execute();
        $result_path = $stmt_path->get_result();
        
        if ($row = $result_path->fetch_assoc()) {
            $file_path = $row['image_path'];
            $full_path = $upload_dir . $file_path;
            
            // 2. Xóa file vật lý
            if (file_exists($full_path) && unlink($full_path)) {
                $deleted = true;
            } elseif (!file_exists($full_path)) {
                $deleted = true; 
            }

            // 3. Xóa bản ghi trong CSDL và RESET AUTO_INCREMENT
            if ($deleted) {
                $sql_delete_img = "DELETE FROM khachsan_images WHERE image_id = ?";
                $stmt_delete = $conn->prepare($sql_delete_img);
                $stmt_delete->bind_param("i", $image_id);
                if ($stmt_delete->execute()) {
                    $message .= "Đã xóa ảnh (ID: {$image_id}) khỏi Khách Sạn (Mã: {$makhs_id}) thành công! ";
                    
                    // RESET AUTO_INCREMENT cho bảng khachsan_images
                    $reset_message = reset_auto_increment($conn, 'khachsan_images', 'image_id');
                    $message .= $reset_message;
                    
                } else {
                    $message .= "Lỗi xóa bản ghi ảnh khỏi CSDL: " . $stmt_delete->error;
                }
                $stmt_delete->close();
            } else {
                $message .= "Lỗi: Không thể xóa file vật lý {$file_path}.";
            }
        } else {
            $message .= "Lỗi: Không tìm thấy bản ghi ảnh với ID: {$image_id}.";
        }
        $stmt_path->close();
    }
    // --------------------------------------------------------
    // --- Xử lý XÓA TOÀN BỘ KHÁCH SẠN (Giữ nguyên và bổ sung logic file) ---
    // --------------------------------------------------------
    elseif ($action == 'delete' && $id) {
        // --- XÓA KHÁCH SẠN ---
        
        $deleted_file_count = 0;
        $reset_message = "";

        // =======================================================
        // BƯỚC 1, 2, 3: XÓA ẢNH VẬT LÝ VÀ BẢN GHI ẢNH (Logic đã cập nhật trước đó)
        // =======================================================
        
        // 1. Lấy danh sách đường dẫn ảnh cần xóa từ CSDL
        $sql_fetch_images = "SELECT image_path FROM khachsan_images WHERE makhs = ?";
        $stmt_fetch = $conn->prepare($sql_fetch_images);
        $stmt_fetch->bind_param("i", $id);
        $stmt_fetch->execute();
        $result_images = $stmt_fetch->get_result();
        $files_to_delete = [];
        while($row = $result_images->fetch_assoc()) {
            $files_to_delete[] = $row['image_path'];
        }
        $stmt_fetch->close();
        
        // 2. Xóa các file vật lý
        foreach ($files_to_delete as $file_path) {
            $full_path = $upload_dir . $file_path;
            if (file_exists($full_path) && unlink($full_path)) {
                $deleted_file_count++;
            }
        }
        // 3. Xóa các bản ghi ảnh trong CSDL VÀ RESET AUTO_INCREMENT
        $sql_delete_images = "DELETE FROM khachsan_images WHERE makhs = ?";
        $stmt_delete_img = $conn->prepare($sql_delete_images);
        $stmt_delete_img->bind_param("i", $id);
        $stmt_delete_img->execute();
        $stmt_delete_img->close();
        
        // BỔ SUNG: RESET AUTO_INCREMENT cho bảng khachsan_images
        $reset_message_img = reset_auto_increment($conn, 'khachsan_images', 'image_id');
        $message .= $reset_message_img;


        // 4. Xóa các phòng liên quan VÀ RESET AUTO_INCREMENT
        $sql_delete_rooms = "DELETE FROM phong WHERE makhs = ?";
        $stmt_rooms = $conn->prepare($sql_delete_rooms);
        $stmt_rooms->bind_param("i", $id);
        $stmt_rooms->execute();
        $stmt_rooms->close();
        
        // BỔ SUNG: RESET AUTO_INCREMENT cho bảng phong
        $reset_message_room = reset_auto_increment($conn, 'phong', 'maphong');
        $message .= $reset_message_room;


        // 5. Xóa khách sạn VÀ RESET AUTO_INCREMENT
        $sql_delete_hotel = "DELETE FROM khachsan_info WHERE makhs = ?";
        $stmt_hotel = $conn->prepare($sql_delete_hotel);
        $stmt_hotel->bind_param("i", $id);
        
        if ($stmt_hotel->execute()) {
            $message .= "Xóa khách sạn (Mã: {$id}) và phòng liên quan thành công! **Đã xóa {$deleted_file_count} file ảnh vật lý.** ";
            
            // RESET AUTO_INCREMENT cho khachsan_info
            $reset_message_ks = reset_auto_increment($conn, 'khachsan_info', 'makhs');
            $message .= $reset_message_ks;
            
        } else {
            $message .= "Lỗi xóa khách sạn: " . $stmt_hotel->error;
        }
        $stmt_hotel->close();
        
    }
    // --------------------------------------------------------
    // --- Xử lý THÊM PHÒNG MỚI (phong) ---
    // --------------------------------------------------------
    elseif ($action == 'add_room') {
        $makhs = (int)clean_input($_POST['MaKhachSan']);
        $sophong = clean_input($_POST['SoPhong']);
        $giaphong = (float)clean_input($_POST['GiaPhong']);
        $loaiphong = clean_input($_POST['LoaiPhong']);
        $trangthai_phong = clean_input($_POST['TrangThaiPhong']);

        $sql_add_room = "INSERT INTO phong (sophong, giaphong, loaiphong, trangthai, makhs) VALUES (?, ?, ?, ?, ?)";
        $stmt_room = $conn->prepare($sql_add_room);
        $stmt_room->bind_param("sdsii", $sophong, $giaphong, $loaiphong, $trangthai_phong, $makhs); 

        if ($stmt_room->execute()) {
            $message .= "Thêm phòng thành công!";
        } else {
            $message .= "Lỗi thêm phòng: " . $stmt_room->error;
        }
        $stmt_room->close();
    }
}

// --------------------------------------------------------
    // --- Xử lý XÓA PHÒNG ĐƠN LẺ (RESET maphong) ---
    // --------------------------------------------------------
    elseif ($action == 'delete_room' && isset($_POST['MaPhong'])) {
        $maphong = (int)$_POST['MaPhong'];

        // 1. Xóa bản ghi phòng
        $sql_delete = "DELETE FROM phong WHERE maphong = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $maphong);

        if ($stmt_delete->execute()) {
            $message .= "Đã xóa phòng (Mã: {$maphong}) thành công! ";
            
            // RESET AUTO_INCREMENT cho bảng phong
            $reset_message = reset_auto_increment($conn, 'phong', 'maphong');
            $message .= $reset_message;
            
        } else {
            $message .= "Lỗi xóa phòng: " . $stmt_delete->error;
        }
        $stmt_delete->close();
    }

// --------------------------------------------------------
// --- PHẦN TRUY VẤN DỮ LIỆU ĐỂ HIỂN THỊ TRÊN HTML ---
// --------------------------------------------------------
// Lấy danh sách khách sạn
$hotels = [];
$sql_hotels = "SELECT makhs, tenks, diachi, mo_ta_chi_tiet FROM khachsan_info";
$result_hotels = $conn->query($sql_hotels);

if ($result_hotels && $result_hotels->num_rows > 0) {
    while($row = $result_hotels->fetch_assoc()) {
        $hotel = $row;
        
        // CẬP NHẬT: Thêm image_id vào truy vấn ảnh
        $sql_images = "SELECT image_id, image_path FROM khachsan_images WHERE makhs = ?";
        $stmt_img_fetch = $conn->prepare($sql_images);
        $stmt_img_fetch->bind_param("i", $hotel['makhs']);
        $stmt_img_fetch->execute();
        $result_images = $stmt_img_fetch->get_result();
        // $hotel['images'] sẽ lưu trữ ID và Path
        $hotel['images'] = [];
        while($img_row = $result_images->fetch_assoc()) {
            $hotel['images'][] = $img_row;
        }
        $stmt_img_fetch->close();

        $hotels[] = $hotel;
    }
}

// Lấy danh sách phòng
// (Giữ nguyên logic phòng cũ)
$rooms = [];
$sql_rooms = "SELECT p.maphong, p.sophong, p.giaphong, p.loaiphong, p.trangthai, p.makhs, k.tenks 
              FROM phong p 
              JOIN khachsan_info k ON p.makhs = k.makhs";
$result_rooms = $conn->query($sql_rooms);
if ($result_rooms && $result_rooms->num_rows > 0) {
    while($row = $result_rooms->fetch_assoc()) {
        $rooms[] = $row;
    }
}

// Đóng kết nối
$conn->close();

// --------------------------------------------------------
// --- PHẦN HTML/JS ---
// --------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Khách Sạn & Phòng (Xóa ảnh đơn lẻ)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .container { max-width: 1200px; }
        .tab-button.active { background-color: #3b82f6; color: white; }
    </style>
</head>
<body class="bg-gray-100 font-sans p-6">
    <div class="fixed bottom-6 right-6 z-10">
        <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-full shadow-2xl transition duration-300 transform hover:scale-105 flex items-center">
            <i class="fas fa-home mr-2"></i> Trở về Trang Chủ
        </a>
    </div>

    <div class="container mx-auto bg-white p-8 rounded-xl shadow-2xl">
        <h1 class="text-4xl font-extrabold text-blue-700 mb-6 border-b-4 border-blue-500 pb-2">Hệ Thống Quản Lý Dữ Liệu</h1>
        
        <?php if (!empty($message)): ?>
            <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="mb-6 flex space-x-2 border-b">
            <button id="tab-hotels" class="tab-button active px-4 py-2 font-semibold rounded-t-lg transition duration-150">Quản Lý Khách Sạn</button>
            <button id="tab-rooms" class="tab-button px-4 py-2 font-semibold rounded-t-lg transition duration-150">Quản Lý Phòng</button>
        </div>

        <div id="content-hotels" class="tab-content">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Thêm Khách Sạn Mới</h2>
            
            <form action="manage.php" method="POST" enctype="multipart/form-data" class="bg-blue-50 p-6 rounded-lg shadow-inner mb-8">
                <input type="hidden" name="action" value="add">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div class="col-span-1">
                        <label for="TenKhachSan" class="block text-sm font-medium text-gray-700">Tên Khách Sạn</label>
                        <input type="text" name="TenKhachSan" id="TenKhachSan" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <div class="col-span-1">
                        <label for="DiaDiem" class="block text-sm font-medium text-gray-700">Địa Chỉ</label>
                        <input type="text" name="DiaDiem" id="DiaDiem" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                     <div class="col-span-1">
                        <label for="HinhAnh" class="block text-sm font-medium text-gray-700">Hình Ảnh (Chọn nhiều)</label>
                        <input type="file" name="HinhAnh[]" id="HinhAnh" multiple class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                    <div class="col-span-1">
                        <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg transition duration-150 shadow-md">
                            <i class="fas fa-plus mr-2"></i> Thêm Khách Sạn
                        </button>
                    </div>
                    
                    <div class="col-span-4">
                        <label for="MoTaChiTiet" class="block text-sm font-medium text-gray-700">Mô Tả Chi Tiết (Văn bản dài)</label>
                        <textarea name="MoTaChiTiet" id="MoTaChiTiet" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border"></textarea>
                    </div>

                </div>
            </form>

            <h2 class="text-2xl font-bold text-gray-800 mb-4">Danh Sách Khách Sạn</h2>
            
            <div class="overflow-x-auto shadow-md rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-blue-500 text-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">STT</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Tên Khách Sạn</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Địa Chỉ</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Mô Tả (Tóm tắt)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Hình Ảnh</th>
                            <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($hotels) > 0): ?>
                            <?php $stt_hotel = 1; ?> 
                            <?php foreach ($hotels as $hotel): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= $stt_hotel++ ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($hotel['tenks']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($hotel['diachi']) ?></td>
                                    <td class="px-6 py-4 max-w-xs text-sm text-gray-500 overflow-hidden text-ellipsis">
                                        <?= htmlspecialchars(substr($hotel['mo_ta_chi_tiet'], 0, 100)) ?>
                                        <?php if (strlen($hotel['mo_ta_chi_tiet']) > 100): ?>
                                            ... <span class="text-blue-500 cursor-pointer text-xs" title="<?= htmlspecialchars($hotel['mo_ta_chi_tiet']) ?>">(Xem thêm)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if (!empty($hotel['images'])): ?>
                                            <div class="flex items-center space-x-2">
                                                <img src="<?= $upload_dir . htmlspecialchars($hotel['images'][0]['image_path']) ?>" 
                                                     alt="Ảnh KS" 
                                                     onerror="this.onerror=null; this.src='https://placehold.co/60x48/CCCCCC/333333?text=No+Image'"
                                                     class="w-16 h-12 object-cover rounded-md shadow-sm">
                                                <span class="text-xs font-semibold text-blue-600">(+<?= count($hotel['images']) - 1 ?> ảnh)</span>
                                            </div>
                                        <?php else: ?>
                                            (Chưa có ảnh)
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-2">
                                        <button onclick="openEditModal(<?= $hotel['makhs'] ?>, 
                                                                    '<?= htmlspecialchars(addslashes($hotel['tenks'])) ?>', 
                                                                    '<?= htmlspecialchars(addslashes($hotel['diachi'])) ?>', 
                                                                    '<?= htmlspecialchars(addslashes($hotel['mo_ta_chi_tiet'])) ?>',
                                                                    '<?= htmlspecialchars(json_encode($hotel['images'], JSON_HEX_QUOT)) ?>')" 
                                                class="text-indigo-600 hover:text-indigo-900 bg-indigo-100 px-3 py-1 rounded-full font-bold">Sửa</button>
                                        
                                        <form method="POST" action="manage.php" class="inline-block" onsubmit="return confirm('Bạn có chắc chắn muốn xóa khách sạn này, TẤT CẢ các phòng và ảnh liên quan?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="MaKhachSan" value="<?= $hotel['makhs'] ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900 bg-red-100 px-3 py-1 rounded-full font-bold">Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">Chưa có khách sạn nào được thêm vào.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden items-center justify-center z-50">
                <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-2xl">
                    <h3 class="text-xl font-bold mb-4 text-blue-700">Cập Nhật Thông Tin Khách Sạn</h3>
                    
                    <form id="editForm" action="manage.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="MaKhachSan" id="editMaKhachSan">

                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-1">
                                <label for="editTenKhachSan" class="block text-sm font-medium text-gray-700">Tên Khách Sạn</label>
                                <input type="text" name="TenKhachSan" id="editTenKhachSan" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                            </div>
                            <div class="col-span-1">
                                <label for="editDiaDiem" class="block text-sm font-medium text-gray-700">Địa Chỉ</label>
                                <input type="text" name="DiaDiem" id="editDiaDiem" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                            </div>
                            <div class="col-span-2">
                                <label for="editMoTaChiTiet" class="block text-sm font-medium text-gray-700">Mô Tả Chi Tiết</label>
                                <textarea name="MoTaChiTiet" id="editMoTaChiTiet" rows="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border"></textarea>
                            </div>
                        </div>

                        <div class="mt-6 border-t pt-4">
                            <h4 class="text-lg font-semibold text-gray-800 mb-2">Thêm Ảnh Mới</h4>
                            <input type="file" name="HinhAnh[]" id="editHinhAnh" multiple class="mt-1 block w-full text-sm text-gray-500">
                            <p class="text-xs text-red-500 mt-1">Lưu ý: Chỉ thêm ảnh mới, không thay thế ảnh cũ.</p>
                        </div>
                        
                        <div class="flex justify-end space-x-3 mt-6">
                            <button type="button" onclick="closeEditModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg transition duration-150">Hủy</button>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150">Lưu Thay Đổi</button>
                        </div>
                    </form>
                    
                    <div class="mt-8 pt-6 border-t border-red-300">
                        <h4 class="text-lg font-bold text-red-700 mb-3"><i class="fas fa-trash-alt mr-2"></i> Quản Lý Ảnh Hiện Tại (Xóa từng ảnh)</h4>
                        <div id="image-deletion-list" class="grid grid-cols-4 gap-3">
                            </div>
                        <p id="no-images-text" class="text-gray-500 text-sm italic mt-2 hidden">Không có ảnh nào hiện tại để xóa.</p>
                    </div>

                </div>
            </div>
            
        </div>

        <div id="content-rooms" class="tab-content hidden">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Thêm Phòng Mới</h2>
            
            <form action="manage.php" method="POST" class="bg-yellow-50 p-6 rounded-lg shadow-inner mb-8">
                <input type="hidden" name="action" value="add_room">
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                    <div class="col-span-1 md:col-span-1">
                        <label for="roomMaKhachSan" class="block text-sm font-medium text-gray-700">Khách Sạn (Thuộc)</label>
                        <select name="MaKhachSan" id="roomMaKhachSan" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                            <option value="">-- Chọn KS --</option>
                            <?php foreach ($hotels as $hotel): ?>
                                <option value="<?= $hotel['makhs'] ?>"><?= htmlspecialchars($hotel['tenks']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-span-1 md:col-span-1">
                        <label for="SoPhong" class="block text-sm font-medium text-gray-700">Số Phòng</label>
                        <input type="text" name="SoPhong" id="SoPhong" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <div class="col-span-1 md:col-span-1">
                        <label for="LoaiPhong" class="block text-sm font-medium text-gray-700">Loại Phòng</label>
                        <select name="LoaiPhong" id="LoaiPhong" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                            <option value="Standard">Standard</option>
                            <option value="Deluxe">Deluxe</option>
                            <option value="Suite">Suite</option>
                        </select>
                    </div>
                    <div class="col-span-1 md:col-span-1">
                        <label for="GiaPhong" class="block text-sm font-medium text-gray-700">Giá Phòng (VND)</label>
                        <input type="number" name="GiaPhong" id="GiaPhong" required min="10000" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <div class="col-span-1 md:col-span-1">
                        <label for="TrangThaiPhong" class="block text-sm font-medium text-gray-700">Trạng Thái</label>
                        <select name="TrangThaiPhong" id="TrangThaiPhong" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                            <option value="1">Sẵn sàng (1)</option>
                            <option value="0">Bận (0)</option>
                            <option value="2">Bảo trì (2)</option>
                        </select>
                    </div>
                    <div class="col-span-1 md:col-span-1">
                        <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded-lg transition duration-150 shadow-md">
                            <i class="fas fa-plus mr-2"></i> Thêm Phòng
                        </button>
                    </div>
                </div>
            </form>

            <h2 class="text-2xl font-bold text-gray-800 mb-4">Danh Sách Phòng</h2>
            
            <div class="overflow-x-auto shadow-md rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-orange-500 text-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">STT</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Khách Sạn</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Số Phòng</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Loại Phòng</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider">Giá</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Trạng Thái</th>
                            <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($rooms) > 0): ?>
                            <?php $stt_room = 1; ?>
                            <?php foreach ($rooms as $room): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= $stt_room++ ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($room['tenks']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($room['sophong']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($room['loaiphong']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right"><?= number_format($room['giaphong'], 0, ',', '.') ?> VND</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php if ($room['trangthai'] == 1) echo 'bg-green-100 text-green-800'; 
                                                  elseif ($room['trangthai'] == 0) echo 'bg-red-100 text-red-800';
                                                  else echo 'bg-yellow-100 text-yellow-800'; ?>">
                                            <?= $room['trangthai'] == 1 ? 'Sẵn sàng' : ($room['trangthai'] == 0 ? 'Bận' : 'Bảo trì') ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                        <button class="text-indigo-600 hover:text-indigo-900 bg-indigo-100 px-3 py-1 rounded-full font-bold">Sửa</button>
                                        <button class="text-red-600 hover:text-red-900 bg-red-100 px-3 py-1 rounded-full font-bold">Xóa</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-gray-500">Chưa có phòng nào được thêm vào.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const uploadDir = '<?= $upload_dir ?>';
        
        // Hàm đóng modal
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editModal').classList.remove('flex');
        }

        // Hàm mở Modal Sửa Khách Sạn
        // Đã thêm images_json để nhận mảng ảnh
        function openEditModal(makhs, tenks, diachi, mo_ta, images_json) {
            document.getElementById('editMaKhachSan').value = makhs;
            document.getElementById('editTenKhachSan').value = tenks;
            document.getElementById('editDiaDiem').value = diachi;
            document.getElementById('editMoTaChiTiet').value = mo_ta; 
            document.getElementById('editHinhAnh').value = ''; 
            
            // Xử lý hiển thị ảnh hiện tại cho phần xóa
            const imageListDiv = document.getElementById('image-deletion-list');
            const noImagesText = document.getElementById('no-images-text');
            imageListDiv.innerHTML = ''; // Xóa nội dung cũ
            
            try {
                const images = JSON.parse(images_json);
                
                if (images && images.length > 0) {
                    noImagesText.classList.add('hidden');
                    images.forEach(image => {
                        // Tạo form cho từng ảnh để gửi yêu cầu xóa riêng biệt
                        const formHtml = `
                            <div class="relative group bg-gray-100 rounded-lg overflow-hidden shadow-sm hover:shadow-md transition duration-200">
                                <img src="${uploadDir}${image.image_path}" 
                                     alt="Ảnh ${image.image_id}" 
                                     class="w-full h-24 object-cover">
                                <form method="POST" action="manage.php" class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 bg-black bg-opacity-50 transition duration-300" 
                                      onsubmit="return confirm('Bạn có chắc muốn xóa ảnh này (ID: ${image.image_id})?');">
                                    <input type="hidden" name="action" value="delete_image">
                                    <input type="hidden" name="image_id" value="${image.image_id}">
                                    <input type="hidden" name="makhs" value="${makhs}">
                                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold p-2 rounded-full text-xs">
                                        <i class="fas fa-times"></i> Xóa ảnh
                                    </button>
                                </form>
                            </div>
                        `;
                        imageListDiv.insertAdjacentHTML('beforeend', formHtml);
                    });
                } else {
                    noImagesText.classList.remove('hidden');
                }
            } catch (e) {
                console.error("Lỗi parse JSON ảnh:", e);
                noImagesText.classList.remove('hidden');
                noImagesText.innerText = "Lỗi tải ảnh hoặc không có ảnh hiện tại để xóa.";
            }

            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').classList.add('flex');
        }

        // Logic chuyển Tab
        document.getElementById('tab-hotels').addEventListener('click', function() {
            document.getElementById('tab-hotels').classList.add('active');
            document.getElementById('tab-rooms').classList.remove('active');
            document.getElementById('content-hotels').classList.remove('hidden');
            document.getElementById('content-rooms').classList.add('hidden');
        });

        document.getElementById('tab-rooms').addEventListener('click', function() {
            document.getElementById('tab-rooms').classList.add('active');
            document.getElementById('tab-hotels').classList.remove('active');
            document.getElementById('content-rooms').classList.remove('hidden');
            document.getElementById('content-hotels').classList.add('hidden');
        });
    </script>
</body>
</html>