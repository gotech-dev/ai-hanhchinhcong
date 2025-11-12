# 📘 HƯỚNG DẪN: Tạo Template DOCX Với Placeholders

**Dành cho:** Admin  
**Mục đích:** Tạo template DOCX đúng cách để chatbot có thể điền nội dung tự động

---

## 🎯 MỤC TIÊU

Tạo file DOCX template có **placeholders** (biến động) để chatbot có thể:
1. Nhận dạng các trường cần điền
2. Tự động điền nội dung do AI tạo
3. Giữ nguyên format và style của template

---

## 📋 QUY TẮC PLACEHOLDERS

### 1. Cú Pháp Placeholder

Placeholder phải có dạng: **`${tên_biến}`**

**✅ ĐÚNG:**
```
${ten_co_quan}
${so_van_ban}
${ngay_thang}
${noi_dung}
```

**❌ SAI:**
```
[ten_co_quan]         ← Sai, phải dùng ${}
{{ten_co_quan}}       ← Sai, phải dùng ${}
tên cơ quan           ← Sai, phải có ${} và không dấu
${tên cơ quan}        ← Sai, phải không dấu và dùng _
```

### 2. Quy Tắc Đặt Tên Biến

- ✅ Chỉ dùng chữ thường (`a-z`)
- ✅ Chỉ dùng số (`0-9`)
- ✅ Chỉ dùng gạch dưới (`_`)
- ❌ KHÔNG dùng chữ in hoa
- ❌ KHÔNG dùng dấu (á, à, ả, ã, ạ, etc.)
- ❌ KHÔNG dùng khoảng trắng
- ❌ KHÔNG dùng ký tự đặc biệt (@, #, $, %, etc.)

**Ví dụ:**
```
✅ ${ten_co_quan}
✅ ${so_van_ban}
✅ ${dia_diem_1}
❌ ${TenCoQuan}          ← Sai: Chữ in hoa
❌ ${tên_cơ_quan}        ← Sai: Có dấu
❌ ${ten co quan}        ← Sai: Có khoảng trắng
❌ ${ten-co-quan}        ← Sai: Dùng dấu gạch ngang
```

### 3. Danh Sách Placeholders Chuẩn

#### Các Trường Cơ Bản (Common)
```
${ten_co_quan}          - Tên cơ quan, tổ chức
${dia_chi}              - Địa chỉ cơ quan
${so_van_ban}           - Số văn bản
${ngay_thang}           - Ngày tháng đầy đủ (VD: 09/11/2025)
${ngay}                 - Ngày (VD: 09)
${thang}                - Tháng (VD: 11)
${nam}                  - Năm (VD: 2025)
${nguoi_ky}             - Người ký
${chuc_vu}              - Chức vụ người ký
```

#### Biên Bản
```
${ten_bien_ban}         - Tiêu đề biên bản
${dia_diem}             - Địa điểm họp
${thoi_gian_bat_dau}    - Thời gian bắt đầu
${thoi_gian_ket_thuc}   - Thời gian kết thúc
${thanh_phan}           - Thành phần tham dự
${noi_dung}             - Nội dung biên bản
${ket_luan}             - Kết luận
${chu_toa}              - Chủ tọa
${thu_ky}               - Thư ký
```

#### Công Văn
```
${so_cong_van}          - Số công văn
${noi_nhan}             - Nơi nhận
${noi_gui}              - Nơi gửi
${trich_yeu}            - Trích yếu
${mo_dau}               - Phần mở đầu
${noi_dung}             - Nội dung chính
${ket_luan}             - Phần kết luận
```

#### Quyết Định
```
${so_quyet_dinh}        - Số quyết định
${can_cu}               - Căn cứ pháp lý
${xet_de_nghi}          - Xét đề nghị
${quyet_dinh}           - Nội dung quyết định
${hieu_luc}             - Hiệu lực thi hành
```

#### Tờ Trình
```
${so_to_trinh}          - Số tờ trình
${noi_gui}              - Nơi gửi
${muc_dich}             - Mục đích
${thoi_gian}            - Thời gian
${dia_diem}             - Địa điểm
${thanh_phan}           - Thành phần
${du_toan}              - Dự toán kinh phí
```

---

## 📝 MẪU TEMPLATE BIÊN BẢN

### File: `template_bien_ban.docx`

```
${ten_co_quan}
${dia_chi}

CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM
Độc lập - Tự do - Hạnh phúc
----------

BIÊN BẢN
${ten_bien_ban}

Số: ${so_van_ban}
Địa điểm: ${dia_diem}
Thời gian bắt đầu: ${thoi_gian_bat_dau}
Thời gian kết thúc: ${thoi_gian_ket_thuc}

THÀNH PHẦN THAM DỰ:
${thanh_phan}

NỘI DUNG CUỘC HỌP:
${noi_dung}

KẾT LUẬN:
${ket_luan}


              CHỦ TỌA                                THƯ KÝ
         (Ký, ghi rõ họ tên)                  (Ký, ghi rõ họ tên)

           ${chu_toa}                              ${thu_ky}
```

---

## 📝 MẪU TEMPLATE CÔNG VĂN

### File: `template_cong_van.docx`

```
${ten_co_quan}                      CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM
${dia_chi}                                 Độc lập - Tự do - Hạnh phúc
----------                                         ----------

Số: ${so_cong_van}                          ${dia_diem}, ngày ${ngay} tháng ${thang} năm ${nam}


CÔNG VĂN
${trich_yeu}


Kính gửi: ${noi_nhan}


${mo_dau}

${noi_dung}

${ket_luan}


                                                    NGƯỜI KÝ
                                                 (Ký, ghi rõ họ tên)


                                                   ${nguoi_ky}
                                                   ${chuc_vu}


Nơi nhận:
- ${noi_nhan};
- Lưu: VT, ${ten_co_quan}.
```

---

## 📝 MẪU TEMPLATE QUYẾT ĐỊNH

### File: `template_quyet_dinh.docx`

```
${ten_co_quan}                      CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM
${dia_chi}                                 Độc lập - Tự do - Hạnh phúc
----------                                         ----------

Số: ${so_quyet_dinh}                        ${dia_diem}, ngày ${ngay} tháng ${thang} năm ${nam}


QUYẾT ĐỊNH
${ten_quyet_dinh}


GIÁM ĐỐC ${ten_co_quan}


Căn cứ ${can_cu};

Xét đề nghị của ${xet_de_nghi},


QUYẾT ĐỊNH:

Điều 1. ${quyet_dinh}

Điều 2. Quyết định này có hiệu lực kể từ ngày ${hieu_luc}.

Điều 3. ${dieu_3}


                                                    GIÁM ĐỐC
                                                 (Ký, ghi rõ họ tên)


                                                   ${nguoi_ky}


Nơi nhận:
- ${noi_nhan};
- Lưu: VT, ${ten_co_quan}.
```

---

## 🔧 CÁCH TẠO TEMPLATE TRONG MICROSOFT WORD

### Bước 1: Mở Microsoft Word

1. Mở Microsoft Word
2. Tạo file mới

### Bước 2: Soạn Thảo Template

1. Gõ nội dung template như bình thường
2. **Quan trọng:** Tại các vị trí cần điền tự động, gõ placeholder dạng `${tên_biến}`

**Ví dụ:**
```
Thay vì gõ:     "Tên cơ quan: _____________"
Hãy gõ:         "Tên cơ quan: ${ten_co_quan}"

Thay vì gõ:     "Số văn bản: _____/____"
Hãy gõ:         "Số văn bản: ${so_van_ban}"
```

### Bước 3: Định Dạng (Format)

- ✅ Có thể định dạng bình thường (bold, italic, font, size, color)
- ✅ Có thể dùng bảng (table)
- ✅ Có thể căn lề (alignment)
- ✅ Placeholder sẽ kế thừa format của text xung quanh

**Ví dụ:**
```
Nếu gõ: "${ten_co_quan}" với font Times New Roman, size 14, bold
→ Khi điền tự động, text sẽ có font Times New Roman, size 14, bold
```

### Bước 4: Lưu File

1. File → Save As
2. **Quan trọng:** Chọn định dạng **Word Document (.docx)**
   - ❌ KHÔNG lưu dạng .doc (old format)
   - ❌ KHÔNG lưu dạng .pdf
3. Đặt tên file: `template_bien_ban.docx` (hoặc tên phù hợp)
4. Click Save

---

## ⚠️ LƯU Ý QUAN TRỌNG

### 1. Định Dạng File

- ✅ **CHỈ** upload file `.docx` (Word 2007+)
- ❌ KHÔNG upload file `.doc` (Word 97-2003) - không hỗ trợ
- ❌ KHÔNG upload file `.pdf` - không thể điền placeholder

### 2. Kiểm Tra Trước Khi Upload

**Checklist:**
- [ ] File có định dạng `.docx`
- [ ] Tất cả placeholders có dạng `${tên_biến}`
- [ ] Tên biến chỉ dùng chữ thường, số, gạch dưới
- [ ] Không có chữ in hoa, dấu, khoảng trắng trong tên biến
- [ ] Template đã được format đẹp (font, size, color, alignment)

### 3. Test Template

Sau khi upload:
1. Vào chatbot
2. Yêu cầu: "Tạo 1 mẫu [loại văn bản]"
3. Kiểm tra xem nội dung có được điền đúng không
4. Tải file DOCX về và kiểm tra format

---

## 🆘 TROUBLESHOOTING

### Vấn Đề 1: Placeholder Không Được Thay Thế

**Triệu chứng:** File DOCX vẫn hiển thị `${ten_co_quan}` thay vì tên cơ quan thực tế

**Nguyên nhân có thể:**
1. ❌ Placeholder có format sai (VD: `{ten_co_quan}` thay vì `${ten_co_quan}`)
2. ❌ Tên biến có chữ in hoa (VD: `${TenCoQuan}`)
3. ❌ Tên biến có dấu (VD: `${tên_cơ_quan}`)
4. ❌ File upload là `.doc` thay vì `.docx`

**Giải pháp:**
- Kiểm tra lại format placeholder
- Đảm bảo tên biến theo đúng quy tắc
- Lưu lại file dạng `.docx` và upload lại

### Vấn Đề 2: File DOCX Bị Lỗi Format

**Triệu chứng:** File mở ra bị vỡ layout, mất format

**Nguyên nhân có thể:**
1. ❌ File `.doc` được đổi tên thành `.docx`
2. ❌ File bị corrupt

**Giải pháp:**
- Mở file bằng Microsoft Word
- File → Save As → chọn `.docx`
- Upload lại

### Vấn Đề 3: Template Không Tìm Thấy

**Triệu chứng:** Chatbot báo "Không tìm thấy template"

**Nguyên nhân có thể:**
1. ❌ Template chưa upload
2. ❌ Template bị inactive
3. ❌ Document type không khớp

**Giải pháp:**
- Kiểm tra lại admin panel
- Đảm bảo template đã upload và active
- Kiểm tra document type của template

---

## 📞 HỖ TRỢ

Nếu cần hỗ trợ thêm:
1. Kiểm tra log: `storage/logs/laravel.log`
2. Chạy test: `php test-template-placeholders.php`
3. Xem báo cáo: `BAO-CAO-CHINH-THUC-VAN-DE-TEMPLATE.md`

---

## 📎 TẢI MẪU TEMPLATE

**Download template mẫu:**
- [Template Biên Bản](./templates/mau_bien_ban.docx)
- [Template Công Văn](./templates/mau_cong_van.docx)
- [Template Quyết Định](./templates/mau_quyet_dinh.docx)

**Cách dùng:**
1. Tải file mẫu về
2. Mở bằng Microsoft Word
3. Chỉnh sửa theo nhu cầu (giữ nguyên placeholders)
4. Lưu và upload lên hệ thống

---

**Chúc bạn tạo template thành công! 🎉**



