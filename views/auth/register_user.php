<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/public/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>สมัครสมาชิก - Cenmulet</title>
    <style>
        .address-fields {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .address-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        .address-row.full {
            grid-template-columns: 1fr;
        }
        .address-row-4col {
            grid-template-columns: repeat(4, 1fr);
        }
        .address-field {
            display: flex;
            flex-direction: column;
        }
        .address-field label {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .address-field input,
        .address-field select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            font-family: inherit;
        }
        .address-field input:focus,
        .address-field select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .address-field select:disabled {
            background-color: #e8eef0;
            color: #999;
            cursor: not-allowed;
        }
        .address-summary {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 12px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 13px;
            color: #0c5a96;
            min-height: 40px;
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo">
        <div class="content-logo">
            <img src="/public/images/image.png" alt="Cenmulet">
            <h2>Cenmulet</h2>
        </div>
        <p>Amulet market place ตลาดพระเครื่อง</p>
    </div>
</div>

<div class="auth-page">
    <div class="auth-box wide">
        <div class="auth-header">
            <div class="icon-wrap"><i class="fa-solid fa-user-plus"></i></div>
            <h1>สมัครสมาชิกผู้ใช้</h1>
            <p>เริ่มต้นใช้งาน Cenmulet วันนี้</p>
        </div>
        <div class="auth-body">

            <?php if (isset($_GET['error'])): ?>
            <div class="error-message">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?php echo $_GET['error'] === 'username_exists' ? 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว' : 'เกิดข้อผิดพลาดในการสมัครสมาชิก'; ?></span>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <i class="fa-solid fa-circle-check"></i>
                <span>สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ</span>
            </div>
            <?php endif; ?>

            <form action="/auth/register_user_process.php" method="POST" enctype="multipart/form-data">

                <!-- ข้อมูลส่วนตัว -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fa-solid fa-user"></i> ข้อมูลส่วนตัว
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fullname">ชื่อ-นามสกุล <span style="color:red">*</span></label>
                            <input type="text" name="fullname" id="fullname" placeholder="กรอกชื่อ-นามสกุล" required>
                        </div>
                        <div class="form-group">
                            <label for="tel">เบอร์โทรศัพท์ <span style="color:red">*</span></label>
                            <input type="tel" name="tel" id="tel" placeholder="กรอกเบอร์โทรศัพท์" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="id_per">เลขบัตรประชาชน <span style="color:red">*</span></label>
                            <input type="text" name="id_per" id="id_per" placeholder="13 หลัก" maxlength="13" required>
                        </div>
                        <div class="form-group">
                            <label for="image">รูปโปรไฟล์</label>
                            <div class="file-input-wrapper">
                                <input type="file" name="image" id="image" accept="image/*">
                                <label for="image" class="file-input-label">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <span id="image-name">เลือกรูปภาพ</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ข้อมูลที่อยู่ -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fa-solid fa-location-dot"></i> ที่อยู่สำหรับจัดส่ง
                    </div>
                    
                    <div class="address-fields">
                        <!-- เลือกจังหวัด/อำเภอ/ตำบล/รหัสไปรษณีย์ -->
                        <div class="address-row-4col">
                            <div class="address-field">
                                <label for="province">จังหวัด <span style="color:red">*</span></label>
                                <select id="province" name="province" required>
                                    <option value="">-- เลือกจังหวัด --</option>
                                </select>
                            </div>
                            <div class="address-field">
                                <label for="district">อำเภอ <span style="color:red">*</span></label>
                                <select id="district" name="district" required disabled>
                                    <option value="">-- เลือกอำเภอ --</option>
                                </select>
                            </div>
                            <div class="address-field">
                                <label for="subdistrict">ตำบล <span style="color:red">*</span></label>
                                <select id="subdistrict" name="subdistrict" required disabled>
                                    <option value="">-- เลือกตำบล --</option>
                                </select>
                            </div>
                            <div class="address-field">
                                <label for="postalCode">รหัสไปรษณีย์ <span style="color:red">*</span></label>
                                <input type="text" id="postalCode" name="postalCode" placeholder="อัตโนมัติ" readonly required>
                            </div>
                        </div>

                        <!-- เลขที่บ้าน -->
                        <div class="address-row full">
                            <div class="address-field">
                                <label for="house_number">บ้านเลขที่ <span style="color:red">*</span></label>
                                <input type="text" id="house_number" name="house_number" placeholder="เช่น 123 หมู่ 4 ซอย XXX" required>
                            </div>
                        </div>

                        <!-- แสดงรายสรุปที่อยู่ -->
                        <div class="address-summary">
                            <i class="fa-solid fa-map-pin"></i>
                            <span style="margin-left: 10px;" id="addressSummary">กรุณาเลือกจังหวัด อำเภอ ตำบล และระบุบ้านเลขที่</span>
                        </div>
                    </div>

                    <!-- ฟิลด์ซ่อนสำหรับบันทึกที่อยู่ที่สมบูรณ์ -->
                    <input type="hidden" id="address" name="address" required>
                </div>

                <!-- ข้อมูลบัญชี -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fa-solid fa-lock"></i> ข้อมูลบัญชี
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">ชื่อผู้ใช้ <span style="color:red">*</span></label>
                            <input type="text" name="username" id="username" placeholder="กรอกชื่อผู้ใช้" required>
                        </div>
                        <div class="form-group">
                            <label for="password">รหัสผ่าน <span style="color:red">*</span></label>
                            <input type="password" name="password" id="password" placeholder="กรอกรหัสผ่าน" required>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-user-plus"></i> สมัครสมาชิก
                </button>
            </form>

            <div class="auth-footer">
                มีบัญชีอยู่แล้ว? <a href="/views/auth/login.php">เข้าสู่ระบบ</a>
            </div>
        </div>
    </div>
</div>

<script>
let geographyData = [];

async function loadGeographyData() {
    try {
        const response = await fetch('/data/geography.json');
        geographyData = await response.json();
        initializeProvinces();
    } catch (error) {
        console.error('Error loading geography data:', error);
    }
}

function initializeProvinces() {
    const uniqueProvinces = [];
    const provinceMap = {};

    geographyData.forEach(item => {
        if (!provinceMap[item.provinceCode]) {
            provinceMap[item.provinceCode] = true;
            uniqueProvinces.push({
                code: item.provinceCode,
                name: item.provinceNameTh
            });
        }
    });

    uniqueProvinces.sort((a, b) => a.code - b.code);

    const provinceSelect = document.getElementById('province');
    uniqueProvinces.forEach(province => {
        const option = document.createElement('option');
        option.value = province.code;
        option.textContent = province.name;
        provinceSelect.appendChild(option);
    });
}

function updateDistricts() {
    const provinceCode = parseInt(document.getElementById('province').value);
    const districtSelect = document.getElementById('district');
    const subdistrictSelect = document.getElementById('subdistrict');

    districtSelect.innerHTML = '<option value="">-- เลือกอำเภอ --</option>';
    subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>';
    document.getElementById('postalCode').value = '';

    if (!provinceCode) {
        districtSelect.disabled = true;
        subdistrictSelect.disabled = true;
        updateAddressSummary();
        return;
    }

    const districts = [];
    const districtMap = {};

    geographyData.forEach(item => {
        if (item.provinceCode === provinceCode && !districtMap[item.districtCode]) {
            districtMap[item.districtCode] = true;
            districts.push({
                code: item.districtCode,
                name: item.districtNameTh
            });
        }
    });

    districts.sort((a, b) => a.code - b.code);

    districts.forEach(district => {
        const option = document.createElement('option');
        option.value = district.code;
        option.textContent = district.name;
        districtSelect.appendChild(option);
    });

    districtSelect.disabled = false;
    subdistrictSelect.disabled = true;
    updateAddressSummary();
}

function updateSubdistricts() {
    const provinceCode = parseInt(document.getElementById('province').value);
    const districtCode = parseInt(document.getElementById('district').value);
    const subdistrictSelect = document.getElementById('subdistrict');

    subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>';
    document.getElementById('postalCode').value = '';

    if (!districtCode) {
        subdistrictSelect.disabled = true;
        updateAddressSummary();
        return;
    }

    const subdistricts = [];
    const subdistrictMap = {};

    geographyData.forEach(item => {
        if (item.provinceCode === provinceCode && 
            item.districtCode === districtCode && 
            !subdistrictMap[item.subdistrictCode]) {
            
            subdistrictMap[item.subdistrictCode] = true;
            subdistricts.push({
                code: item.subdistrictCode,
                name: item.subdistrictNameTh,
                postalCode: item.postalCode
            });
        }
    });

    subdistricts.sort((a, b) => a.code - b.code);

    subdistricts.forEach(subdistrict => {
        const option = document.createElement('option');
        option.value = subdistrict.code;
        option.textContent = subdistrict.name;
        option.dataset.postalCode = subdistrict.postalCode;
        subdistrictSelect.appendChild(option);
    });

    subdistrictSelect.disabled = false;
    updateAddressSummary();
}

function updatePostalCode() {
    const subdistrictSelect = document.getElementById('subdistrict');
    const selectedOption = subdistrictSelect.options[subdistrictSelect.selectedIndex];
    const postalCode = selectedOption.dataset.postalCode || '';

    document.getElementById('postalCode').value = postalCode;
    updateAddressSummary();
}

function updateAddressSummary() {
    const provinceName = document.getElementById('province').options[document.getElementById('province').selectedIndex].text;
    const districtName = document.getElementById('district').options[document.getElementById('district').selectedIndex].text;
    const subdistrictName = document.getElementById('subdistrict').options[document.getElementById('subdistrict').selectedIndex].text;
    const houseNumber = document.getElementById('house_number').value;
    const postalCode = document.getElementById('postalCode').value;

    const parts = [];
    if (houseNumber) parts.push(houseNumber);
    if (subdistrictName !== '-- เลือกตำบล --') parts.push('ตำบล' + subdistrictName);
    if (districtName !== '-- เลือกอำเภอ --') parts.push('อำเภอ' + districtName);
    if (provinceName !== '-- เลือกจังหวัด --') parts.push(provinceName);
    if (postalCode) parts.push(postalCode);

    const summary = parts.length > 0 ? parts.join(' ') : 'กรุณาเลือกจังหวัด อำเภอ ตำบล และระบุบ้านเลขที่';
    document.getElementById('addressSummary').textContent = summary;

    document.getElementById('address').value = summary;
}

document.getElementById('province').addEventListener('change', updateDistricts);
document.getElementById('district').addEventListener('change', updateSubdistricts);
document.getElementById('subdistrict').addEventListener('change', updatePostalCode);
document.getElementById('house_number').addEventListener('input', updateAddressSummary);

document.getElementById('image').addEventListener('change', function(e) {
    const f = e.target.files[0];
    if (f) document.getElementById('image-name').textContent = f.name;
});

document.getElementById('id_per').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g,'');
});

window.addEventListener('DOMContentLoaded', loadGeographyData);
</script>
</body>
</html>