-- Revert the file-number un-padding applied on 2026-09-25 16:16
UPDATE allocation_list_stage SET file_no = N'RES-2024-004' WHERE id = 660;
UPDATE allocation_list_stage SET file_no = N'RES-2024-005' WHERE id = 661;
UPDATE allocation_list_stage SET file_no = N'RES-2024-006' WHERE id = 662;
UPDATE allocation_list_stage SET file_no = N'RES-2024-007' WHERE id = 663;
UPDATE allocation_list_stage SET file_no = N'RES-2024-008' WHERE id = 664;
UPDATE allocation_list_stage SET file_no = N'RES-2024-010' WHERE id = 666;
UPDATE allocation_list_stage SET file_no = N'RES-2024-012' WHERE id = 668;
UPDATE allocation_list_stage SET file_no = N'RES-2024-009' WHERE id = 670;
UPDATE allocation_list_stage SET file_no = N'RES-2024-011' WHERE id = 671;
UPDATE allocation_list_stage SET file_no = N'RES-2024-013' WHERE id = 672;
