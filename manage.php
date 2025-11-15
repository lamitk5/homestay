<?php
// Đây là file manage_data.php đã được chỉnh sửa để hỗ trợ upload nhiều hình ảnh cho Khách Sạn.
// LƯU Ý: Cần tạo bảng 'hotel_images' trong CSDL trước (xem hướng dẫn ở phần giới thiệu).

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "khachsan";

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
    $data = htmlspecialchars($data);
    return $data;
}

$message = "";

// --------------------------------------------------------
// --- HÀM XỬ LÝ UPLOAD NHIỀU FILE VÀ LƯU VÀO CSDL ---
// --------------------------------------------------------
function handle_multiple_uploads($conn, $makhs, &$message) {
    // Kiểm tra xem có file nào được chọn và là dạng mảng (multiple)
    if (isset($_FILES['HinhAnh']) && is_array($_FILES['HinhAnh']['name'])) {
        $file_count = count($_FILES['HinhAnh']['name']);
        $uploaded_count = 0;
        $target_dir = "uploads/";
        
        // Tạo thư mục nếu chưa tồn tại (chỉ là giả định, trong môi trường thực tế cần đảm bảo thư mục này có quyền ghi)
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        for ($i = 0; $i < $file_count; $i++) {
            // Chỉ xử lý nếu không có lỗi upload và tên file không rỗng
            if ($_FILES["HinhAnh"]["error"][$i] == 0 && !empty($_FILES["HinhAnh"]["name"][$i])) {
                $file_name = basename($_FILES["HinhAnh"]["name"][$i]);
                $temp_name = $_FILES["HinhAnh"]["tmp_name"][$i];
                // Tạo tên file duy nhất: timestamp_id_index_filename
                $new_file_name = time() . '_' . $makhs . '_' . $i . '_' . $file_name; 
                $target_file = $target_dir . $new_file_name;

                if (move_uploaded_file($temp_name, $target_file)) {
                    // Thêm tên file vào bảng hotel_images (đã được tạo)
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
    // --- Xử lý THÊM / SỬA / XÓA Khách Sạn (khachsan_info) ---
    // --------------------------------------------------------
    if (in_array($action, ['add', 'edit'])) {
        $tenks = clean_input($_POST['TenKhachSan']);
        $diachi = clean_input($_POST['DiaDiem']); 
        
        // Bỏ qua logic xử lý HinhAnh cũ vì chúng ta dùng bảng riêng
        
        if ($action == 'add') {
            // --- THÊM KHÁCH SẠN ---
            // Không còn cột HinhAnh trong khachsan_info
            $sql_add = "INSERT INTO khachsan_info (tenks, diachi) VALUES (?, ?)";
            $stmt = $conn->prepare($sql_add);
            $stmt->bind_param("ss", $tenks, $diachi); 

            if ($stmt->execute()) {
                $new_id = $conn->insert_id; // Lấy ID khách sạn mới được thêm
                $message .= "Thêm khách sạn thành công!";
                handle_multiple_uploads($conn, $new_id, $message); // Xử lý upload ảnh
            } else {
                $message .= "Lỗi thêm khách sạn: " . $stmt->error;
            }
            $stmt->close();

        } elseif ($action == 'edit' && $id) {
            // --- SỬA KHÁCH SẠN ---
            // Cập nhật thông tin cơ bản
            $sql_update = "UPDATE khachsan_info SET tenks=?, diachi=? WHERE makhs=?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("ssi", $tenks, $diachi, $id);

            if ($stmt->execute()) {
                $message .= "Cập nhật thông tin khách sạn thành công!";
                // Xử lý upload ảnh: Ảnh mới sẽ được THÊM vào bảng hotel_images
                handle_multiple_uploads($conn, $id, $message); 
            } else {
                $message .= "Lỗi cập nhật khách sạn: " . $stmt->error;
            }
            $stmt->close();
        }
    
    } elseif ($action == 'delete' && $id) {
        // --- XÓA KHÁCH SẠN ---
        // Do đã có ràng buộc ON DELETE CASCADE trên bảng hotel_images và phong (hoặc nên có), 
        // việc xóa hotel sẽ tự động xóa ảnh và phòng.
        
        // 1. Xóa các phòng liên quan
        // (Nếu phòng có khóa ngoại tới datphong thì bạn cần xóa datphong trước)
        $sql_delete_rooms = "DELETE FROM phong WHERE makhs = ?";
        $stmt_rooms = $conn->prepare($sql_delete_rooms);
        $stmt_rooms->bind_param("i", $id);
        $stmt_rooms->execute();
        $stmt_rooms->close();

        // 2. Xóa khách sạn (sẽ tự động xóa ảnh nếu cấu hình ON DELETE CASCADE)
        $sql_delete_hotel = "DELETE FROM khachsan_info WHERE makhs = ?";
        $stmt_hotel = $conn->prepare($sql_delete_hotel);
        $stmt_hotel->bind_param("i", $id);
        
        if ($stmt_hotel->execute()) {
            $message .= "Xóa khách sạn (Mã: {$id}), phòng và ảnh liên quan thành công!";
        } else {
            $message .= "Lỗi xóa khách sạn: " . $stmt_hotel->error;
        }
        $stmt_hotel->close();
        
    // --------------------------------------------------------
    // --- Xử lý THÊM PHÒNG MỚI (phong) ---
    // --------------------------------------------------------
    } elseif ($action == 'add_room') {
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
// --- PHẦN TRUY VẤN DỮ LIỆU ĐỂ HIỂN THỊ TRÊN HTML ---
// --------------------------------------------------------
// Lấy danh sách khách sạn
$hotels = [];
// Bỏ cột HinhAnh khỏi truy vấn khachsan_info
$sql_hotels = "SELECT makhs, tenks, diachi FROM khachsan_info";
$result_hotels = $conn->query($sql_hotels);

if ($result_hotels && $result_hotels->num_rows > 0) {
    while($row = $result_hotels->fetch_assoc()) {
        $hotel = $row;
        
        // Thêm truy vấn để lấy TẤT CẢ ảnh từ bảng hotel_images
        $sql_images = "SELECT * FROM khachsan_images WHERE makhs = ?";
        $stmt_img_fetch = $conn->prepare($sql_images);
        $stmt_img_fetch->bind_param("i", $hotel['makhs']);
        $stmt_img_fetch->execute();
        $result_images = $stmt_img_fetch->get_result();
               $hotel['images'] = [];
        while($img_row = $result_images->fetch_assoc()) {
            $hotel['images'][] = $img_row['image_path'];
        }
        $stmt_img_fetch->close();

        $hotels[] = $hotel;
    }
}

// Lấy danh sách phòng
$rooms = [];
$sql_rooms = "SELECT p.maphong, p.sophong, p.giaphong, p.loaiphong, p.trangthai, k.tenks 
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

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Khách Sạn & Phòng (Multi-Image)</title>
    <!-- Tải Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Tải Font Awesome cho icon -->
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
        <h1 class="text-4xl font-extrabold text-blue-700 mb-6 border-b-4 border-blue-500 pb-2">Hệ Thống Quản Lý Dữ Liệu (Đa Ảnh)</h1>
        
        <!-- Hiển thị thông báo PHP -->
        <?php if (!empty($message)): ?>
            <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Khu vực Tab (Quản lý Khách sạn / Quản lý Phòng) -->
        <div class="mb-6 flex space-x-2 border-b">
            <button id="tab-hotels" class="tab-button active px-4 py-2 font-semibold rounded-t-lg transition duration-150">Quản Lý Khách Sạn</button>
            <button id="tab-rooms" class="tab-button px-4 py-2 font-semibold rounded-t-lg transition duration-150">Quản Lý Phòng</button>
        </div>

        <!-- -------------------------------------------------------- -->
        <!-- --- TAB 1: QUẢN LÝ KHÁCH SẠN (Thêm/Sửa/Xóa) --- -->
        <!-- -------------------------------------------------------- -->
        <div id="content-hotels" class="tab-content">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Thêm Khách Sạn Mới</h2>
            
            <!-- FORM THÊM KHÁCH SẠN -->
            <form action="manage.php" method="POST" enctype="multipart/form-data" class="bg-blue-50 p-6 rounded-lg shadow-inner mb-8">
                <input type="hidden" name="action" value="add">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <!-- Tên Khách Sạn -->
                    <div class="col-span-1 md:col-span-1">
                        <label for="TenKhachSan" class="block text-sm font-medium text-gray-700">Tên Khách Sạn</label>
                        <input type="text" name="TenKhachSan" id="TenKhachSan" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <!-- Địa Chỉ -->
                    <div class="col-span-1 md:col-span-1">
                        <label for="DiaDiem" class="block text-sm font-medium text-gray-700">Địa Chỉ</label>
                        <input type="text" name="DiaDiem" id="DiaDiem" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <!-- Hình Ảnh (Thêm thuộc tính multiple) -->
                    <div class="col-span-1 md:col-span-1">
                        <label for="HinhAnh" class="block text-sm font-medium text-gray-700">Hình Ảnh (Chọn nhiều)</label>
                        <input type="file" name="HinhAnh[]" id="HinhAnh" multiple class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                    <!-- Nút Thêm -->
                    <div class="col-span-1 md:col-span-1">
                        <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg transition duration-150 shadow-md">
                            <i class="fas fa-plus mr-2"></i> Thêm Khách Sạn
                        </button>
                    </div>
                </div>
            </form>

            <h2 class="text-2xl font-bold text-gray-800 mb-4">Danh Sách Khách Sạn</h2>
            
            <!-- BẢNG HIỂN THỊ VÀ CÁC NÚT SỬA/XÓA -->
            <div class="overflow-x-auto shadow-md rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-blue-500 text-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Mã KS</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Tên Khách Sạn</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Địa Chỉ</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Hình Ảnh</th>
                            <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($hotels) > 0): ?>
                            <?php foreach ($hotels as $hotel): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= $hotel['makhs'] ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($hotel['tenks']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($hotel['diachi']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if (!empty($hotel['images'])): ?>
                                            <div class="flex items-center space-x-2">
                                                <img src="uploads/<?= htmlspecialchars($hotel['images'][0]) ?>" 
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
                                        <!-- Nút Sửa: Chỉ truyền thông tin text, không cần HinhAnhCu nữa -->
                                        <button onclick="openEditModal(<?= $hotel['makhs'] ?>, '<?= htmlspecialchars(addslashes($hotel['tenks'])) ?>', '<?= htmlspecialchars(addslashes($hotel['diachi'])) ?>')" class="text-indigo-600 hover:text-indigo-900 bg-indigo-100 px-3 py-1 rounded-full font-bold">Sửa</button>
                                        
                                        <!-- Form Xóa (sử dụng POST an toàn hơn) -->
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
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">Chưa có khách sạn nào được thêm vào.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Modal SỬA KHÁCH SẠN (Ẩn) -->
            <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden items-center justify-center z-50">
                <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-lg">
                    <h3 class="text-xl font-bold mb-4 text-blue-700">Cập Nhật Thông Tin Khách Sạn</h3>
                    <form id="editForm" action="manage.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="MaKhachSan" id="editMaKhachSan">

                        <div class="mb-4">
                            <label for="editTenKhachSan" class="block text-sm font-medium text-gray-700">Tên Khách Sạn</label>
                            <input type="text" name="TenKhachSan" id="editTenKhachSan" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                        </div>
                        <div class="mb-4">
                            <label for="editDiaDiem" class="block text-sm font-medium text-gray-700">Địa Chỉ</label>
                            <input type="text" name="DiaDiem" id="editDiaDiem" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                        </div>
                        
                        <!-- Cập nhật: Chỉ cho phép THÊM ảnh mới, không chỉnh sửa ảnh cũ -->
                        <div class="mb-4 border-t pt-4">
                            <label for="editHinhAnh" class="block text-sm font-medium text-gray-700">Thêm Hình Ảnh Mới (Chọn nhiều)</label>
                            <input type="file" name="HinhAnh[]" id="editHinhAnh" multiple class="mt-1 block w-full text-sm text-gray-500">
                            <p class="text-xs text-red-500 mt-1">Lưu ý: Chức năng này chỉ thêm ảnh mới, không thay thế ảnh cũ.</p>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="document.getElementById('editModal').classList.add('hidden'); document.getElementById('editModal').classList.remove('flex');" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg transition duration-150">Hủy</button>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150">Lưu Thay Đổi</button>
                        </div>
                    </form>
                </div>
            </div>
            
        </div>

        <!-- -------------------------------------------------------- -->
        <!-- --- TAB 2: QUẢN LÝ PHÒNG (Không thay đổi) --- -->
        <!-- -------------------------------------------------------- -->
        <div id="content-rooms" class="tab-content hidden">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Thêm Phòng Mới</h2>
            
            <!-- FORM THÊM PHÒNG -->
            <form action="manage.php" method="POST" class="bg-yellow-50 p-6 rounded-lg shadow-inner mb-8">
                <input type="hidden" name="action" value="add_room">
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                    <!-- Khách Sạn -->
                    <div class="col-span-1 md:col-span-1">
                        <label for="roomMaKhachSan" class="block text-sm font-medium text-gray-700">Khách Sạn (Thuộc)</label>
                        <select name="MaKhachSan" id="roomMaKhachSan" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                            <option value="">-- Chọn KS --</option>
                            <?php foreach ($hotels as $hotel): ?>
                                <option value="<?= $hotel['makhs'] ?>"><?= htmlspecialchars($hotel['tenks']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Số Phòng -->
                    <div class="col-span-1 md:col-span-1">
                        <label for="SoPhong" class="block text-sm font-medium text-gray-700">Số Phòng</label>
                        <input type="text" name="SoPhong" id="SoPhong" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <!-- Loại Phòng -->
                    <div class="col-span-1 md:col-span-1">
                        <label for="LoaiPhong" class="block text-sm font-medium text-gray-700">Loại Phòng</label>
                        <select name="LoaiPhong" id="LoaiPhong" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                            <option value="Standard">Standard</option>
                            <option value="Deluxe">Deluxe</option>
                            <option value="Suite">Suite</option>
                        </select>
                    </div>
                    <!-- Giá Phòng -->
                    <div class="col-span-1 md:col-span-1">
                        <label for="GiaPhong" class="block text-sm font-medium text-gray-700">Giá Phòng (VND)</label>
                        <input type="number" name="GiaPhong" id="GiaPhong" required min="10000" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <!-- Trạng Thái -->
                    <div class="col-span-1 md:col-span-1">
                        <label for="TrangThaiPhong" class="block text-sm font-medium text-gray-700">Trạng Thái</label>
                        <select name="TrangThaiPhong" id="TrangThaiPhong" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                            <option value="1">Sẵn sàng (1)</option>
                            <option value="0">Bận (0)</option>
                            <option value="2">Bảo trì (2)</option>
                        </select>
                    </div>
                    <!-- Nút Thêm -->
                    <div class="col-span-1 md:col-span-1">
                        <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded-lg transition duration-150 shadow-md">
                            <i class="fas fa-plus mr-2"></i> Thêm Phòng
                        </button>
                    </div>
                </div>
            </form>

            <h2 class="text-2xl font-bold text-gray-800 mb-4">Danh Sách Phòng</h2>
            
            <!-- BẢNG HIỂN THỊ PHÒNG -->
            <div class="overflow-x-auto shadow-md rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-orange-500 text-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Mã Phòng</th>
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
                            <?php foreach ($rooms as $room): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= $room['maphong'] ?></td>
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

    <!-- JavaScript cho Modal và Tab -->
    <script>
        // Hàm mở Modal Sửa Khách Sạn (đã loại bỏ các tham số liên quan đến HinhAnh)
        function openEditModal(makhs, tenks, diachi) {
            document.getElementById('editMaKhachSan').value = makhs;
            document.getElementById('editTenKhachSan').value = tenks;
            document.getElementById('editDiaDiem').value = diachi;
            
            // Xóa giá trị file cũ để tránh gửi lại file không cần thiết
            document.getElementById('editHinhAnh').value = ''; 
            
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