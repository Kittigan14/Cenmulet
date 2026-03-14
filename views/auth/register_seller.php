<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../public/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>สมัครร้านค้า - Cenmulet</title>
    <style>
        .address-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .address-section.full {
            grid-template-columns: 1fr;
        }
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
        }
        select:focus {
            outline: none;
            border-color: #a67c52;
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo">
        <div class="content-logo">
            <img src="../../public/images/image.png" alt="Cenmulet">
            <h2>Cenmulet</h2>
        </div>
        <p>Amulet market place ตลาดพระเครื่อง</p>
    </div>
</div>

<div class="auth-page">
    <div class="auth-box wide">
        <div class="auth-header">
            <div class="icon-wrap"><i class="fa-solid fa-store"></i></div>
            <h1>สมัครร้านค้า</h1>
            <p>เริ่มต้นขายพระเครื่องกับ Cenmulet</p>
        </div>
        <div class="auth-body">

            <!-- Notice about approval -->
            <div class="info-message">
                <i class="fa-solid fa-circle-info"></i>
                <span>การสมัครร้านค้าต้องผ่านการตรวจสอบและอนุมัติจากผู้ดูแลระบบก่อน คุณจะได้รับแจ้งผลภายใน 1-3 วันทำการ</span>
            </div>

            <?php if (isset($_GET['error'])): ?>
            <div class="error-message">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span>
                    <?php
                    $errs = [
                        'username_exists' => 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว',
                        'empty'           => 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน',
                        'invalid_file'    => 'ประเภทไฟล์ไม่ถูกต้อง',
                    ];
                    echo $errs[$_GET['error']] ?? 'เกิดข้อผิดพลาดในการสมัครสมาชิก';
                    ?>
                </span>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['success']) && $_GET['success'] === 'pending'): ?>
            <div class="success-message">
                <i class="fa-solid fa-circle-check"></i>
                <span>ส่งข้อมูลสมัครเรียบร้อยแล้ว! ระบบจะแจ้งผลการอนุมัติทางผู้ดูแลระบบ กรุณารอการยืนยัน</span>
            </div>
            <?php endif; ?>

            <form action="../../auth/register_seller_process.php" method="POST" enctype="multipart/form-data">

                <!-- ข้อมูลร้านค้า -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fa-solid fa-store"></i> ข้อมูลร้านค้า
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="store_name">ชื่อร้าน <span style="color:red">*</span></label>
                            <input type="text" name="store_name" id="store_name" placeholder="กรอกชื่อร้าน" required>
                        </div>
                        <div class="form-group">
                            <label for="img_store">รูปร้านค้า</label>
                            <div class="file-input-wrapper">
                                <input type="file" name="img_store" id="img_store" accept="image/*">
                                <label for="img_store" class="file-input-label">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <span id="store-file-name">เลือกรูปร้าน</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Address Section with Dropdowns -->
                    <div class="form-group">
                        <label>ที่อยู่ร้านค้า <span style="color:red">*</span></label>
                    </div>

                    <div class="address-section">
                        <div class="form-group">
                            <label for="house_number">บ้านเลขที่ <span style="color:red">*</span></label>
                            <input type="text" name="house_number" id="house_number" placeholder="กรอกบ้านเลขที่" required>
                        </div>
                        <div class="form-group">
                            <label for="province">จังหวัด <span style="color:red">*</span></label>
                            <select name="province" id="province" required>
                                <option value="">-- เลือกจังหวัด --</option>
                            </select>
                        </div>
                    </div>

                    <div class="address-section">
                        <div class="form-group">
                            <label for="district">อำเภอ <span style="color:red">*</span></label>
                            <select name="district" id="district" required disabled>
                                <option value="">-- เลือกอำเภอ --</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="subdistrict">ตำบล <span style="color:red">*</span></label>
                            <select name="subdistrict" id="subdistrict" required disabled>
                                <option value="">-- เลือกตำบล --</option>
                            </select>
                        </div>
                    </div>

                    <div class="address-section">
                        <div class="form-group">
                            <label for="postal_code">รหัสไปรษณีย์</label>
                            <input type="text" name="postal_code" id="postal_code" placeholder="รหัสไปรษณีย์" readonly>
                        </div>
                    </div>

                    <!-- Hidden field to store full address -->
                    <input type="hidden" name="address" id="address">

                    <!-- ช่องทางการชำระเงิน -->
                    <div class="form-group">
                        <label for="pay_bank">ธนาคาร / ช่องทางการชำระเงิน <span style="color:red">*</span></label>
                        <select name="pay_bank" id="pay_bank" required>
                            <option value="">-- เลือกธนาคาร --</option>
                            <option value="พร้อมเพย์ (PromptPay)">พร้อมเพย์ (PromptPay)</option>
                            <option value="ธนาคารกสิกรไทย (KBank)">ธนาคารกสิกรไทย (KBank)</option>
                            <option value="ธนาคารกรุงไทย (KTB)">ธนาคารกรุงไทย (KTB)</option>
                            <option value="ธนาคารกรุงเทพ (BBL)">ธนาคารกรุงเทพ (BBL)</option>
                            <option value="ธนาคารไทยพาณิชย์ (SCB)">ธนาคารไทยพาณิชย์ (SCB)</option>
                            <option value="ธนาคารกรุงศรีอยุธยา (BAY)">ธนาคารกรุงศรีอยุธยา (BAY)</option>
                            <option value="ธนาคารทหารไทยธนชาต (TTB)">ธนาคารทหารไทยธนชาต (TTB)</option>
                            <option value="ธนาคารออมสิน (GSB)">ธนาคารออมสิน (GSB)</option>
                            <option value="ธนาคารเพื่อการเกษตรและสหกรณ์ (BAAC)">ธนาคารเพื่อการเกษตรและสหกรณ์ (BAAC)</option>
                            <option value="ธนาคารอาคารสงเคราะห์ (GHB)">ธนาคารอาคารสงเคราะห์ (GHB)</option>
                            <option value="ธนาคารซีไอเอ็มบี ไทย (CIMB)">ธนาคารซีไอเอ็มบี ไทย (CIMB)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="pay_contax">เลขที่บัญชี / เบอร์พร้อมเพย์ <span style="color:red">*</span></label>
                        <input type="text" name="pay_contax" id="pay_contax" placeholder="เช่น 123-4-56789-0 หรือ 08X-XXX-XXXX" required>
                    </div>
                </div>

                <!-- ข้อมูลส่วนตัว -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fa-solid fa-id-card"></i> ข้อมูลส่วนตัว (สำหรับยืนยันตัวตน)
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fullname">ชื่อ-นามสกุล <span style="color:red">*</span></label>
                            <input type="text" name="fullname" id="fullname" placeholder="ตามบัตรประชาชน" required>
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
                            <label for="img_per">รูปบัตรประชาชน <span style="color:red">*</span></label>
                            <div class="file-input-wrapper">
                                <input type="file" name="img_per" id="img_per" accept="image/*" required>
                                <label for="img_per" class="file-input-label">
                                    <i class="fa-solid fa-id-card"></i>
                                    <span id="id-file-name">เลือกรูปบัตรประชาชน</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ข้อมูลบัญชี -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fa-solid fa-lock"></i> ข้อมูลบัญชีเข้าสู่ระบบ
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
                    <i class="fa-solid fa-paper-plane"></i> ส่งคำขอสมัครร้านค้า
                </button>
            </form>

            <div class="auth-footer">
                มีบัญชีอยู่แล้ว? <a href="/views/auth/login.php">เข้าสู่ระบบ</a>
            </div>
        </div>
    </div>
</div>

<script>
// Geography data will be loaded dynamically
let geographyData = [];

// Load geography data
async function loadGeographyData() {
    try {
        const response = await fetch('../../data/geography.json');
        geographyData = await response.json();
        initializeProvinces();
    } catch (error) {
        console.error('Error loading geography data:', error);
    }
}

// Initialize provinces dropdown - ไม่มีซ้ำ
function initializeProvinces() {
    const provinceMap = new Map();
    
    // เก็บจังหวัดที่ไม่ซ้ำ
    geographyData.forEach(item => {
        if (!provinceMap.has(item.provinceCode)) {
            provinceMap.set(item.provinceCode, item.provinceNameTh);
        }
    });
    
    // แปลงเป็น array และเรียงลำดับ
    const provinces = Array.from(provinceMap, ([code, name]) => ({ code, name }))
        .sort((a, b) => a.name.localeCompare(b.name, 'th'));

    const provinceSelect = document.getElementById('province');
    provinces.forEach(province => {
        const option = document.createElement('option');
        option.value = province.code;
        option.textContent = province.name;
        provinceSelect.appendChild(option);
    });
}

// Update districts when province changes - ไม่มีซ้ำ
document.getElementById('province').addEventListener('change', function() {
    const provinceCode = this.value;
    const districtSelect = document.getElementById('district');
    const subdistrictSelect = document.getElementById('subdistrict');
    
    // Reset dropdowns
    districtSelect.innerHTML = '<option value="">-- เลือกอำเภอ --</option>';
    subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>';
    document.getElementById('postal_code').value = '';
    
    if (!provinceCode) {
        districtSelect.disabled = true;
        subdistrictSelect.disabled = true;
        return;
    }

    // Get unique districts for selected province
    const districtMap = new Map();
    geographyData
        .filter(item => item.provinceCode == provinceCode)
        .forEach(item => {
            if (!districtMap.has(item.districtCode)) {
                districtMap.set(item.districtCode, item.districtNameTh);
            }
        });
    
    const districts = Array.from(districtMap, ([code, name]) => ({ code, name }))
        .sort((a, b) => a.name.localeCompare(b.name, 'th'));

    districts.forEach(district => {
        const option = document.createElement('option');
        option.value = district.code;
        option.textContent = district.name;
        districtSelect.appendChild(option);
    });

    districtSelect.disabled = false;
});

// Update subdistricts when district changes - ไม่มีซ้ำ
document.getElementById('district').addEventListener('change', function() {
    const provinceCode = document.getElementById('province').value;
    const districtCode = this.value;
    const subdistrictSelect = document.getElementById('subdistrict');
    
    // Reset subdistrict dropdown
    subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>';
    document.getElementById('postal_code').value = '';
    
    if (!districtCode) {
        subdistrictSelect.disabled = true;
        return;
    }

    // Get subdistricts for selected district - ไม่มีซ้ำ
    const subdistrictMap = new Map();
    geographyData
        .filter(item => item.provinceCode == provinceCode && item.districtCode == districtCode)
        .forEach(item => {
            if (!subdistrictMap.has(item.subdistrictCode)) {
                subdistrictMap.set(item.subdistrictCode, {
                    name: item.subdistrictNameTh,
                    postal: item.postalCode
                });
            }
        });
    
    const subdistricts = Array.from(subdistrictMap, ([code, data]) => ({ 
        code, 
        name: data.name, 
        postal: data.postal 
    })).sort((a, b) => a.name.localeCompare(b.name, 'th'));

    subdistricts.forEach(subdistrict => {
        const option = document.createElement('option');
        option.value = subdistrict.code;
        option.textContent = subdistrict.name;
        option.dataset.postal = subdistrict.postal;
        subdistrictSelect.appendChild(option);
    });

    subdistrictSelect.disabled = false;
});

// Update postal code when subdistrict changes
document.getElementById('subdistrict').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    document.getElementById('postal_code').value = selectedOption.dataset.postal || '';
    updateAddressField();
});

// Update address field
function updateAddressField() {
    const houseNumber = document.getElementById('house_number').value;
    const provinceCode = document.getElementById('province').value;
    const districtCode = document.getElementById('district').value;
    const subdistrictCode = document.getElementById('subdistrict').value;

    if (!houseNumber || !provinceCode || !districtCode || !subdistrictCode) {
        document.getElementById('address').value = '';
        return;
    }

    const province = geographyData.find(item => item.provinceCode == provinceCode);
    const district = geographyData.find(item => item.districtCode == districtCode);
    const subdistrict = geographyData.find(item => item.subdistrictCode == subdistrictCode);

    if (province && district && subdistrict) {
        const fullAddress = `บ้านเลขที่ ${houseNumber} ${subdistrict.subdistrictNameTh} ${district.districtNameTh} ${province.provinceNameTh} ${subdistrict.postalCode}`;
        document.getElementById('address').value = fullAddress;
    }
}

// Listen to house number changes
document.getElementById('house_number').addEventListener('change', updateAddressField);
document.getElementById('subdistrict').addEventListener('change', updateAddressField);

// File input handlers
document.getElementById('img_store').addEventListener('change', function(e) {
    const f = e.target.files[0];
    if (f) document.getElementById('store-file-name').textContent = f.name;
});

document.getElementById('img_per').addEventListener('change', function(e) {
    const f = e.target.files[0];
    if (f) document.getElementById('id-file-name').textContent = f.name;
});

document.getElementById('id_per').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g,'');
});

// Load data on page load
window.addEventListener('load', loadGeographyData);
</script>
</body>
</html>