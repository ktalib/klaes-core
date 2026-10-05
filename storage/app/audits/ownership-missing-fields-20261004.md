# Change of Ownership missing-field investigation

Date: 2026-10-04 (Africa/Lagos). Read-only investigation; no application records changed.

| File | Finding |
| --- | --- |
| RES-2025-1097 | OP serial blank in PRA 119766, linked Transfer of Title 221861 and OSS 37561. No corroborated serial found. Indexing 91024 names FATIMA MUHAMMAD as original holder, while the OP names FATIMA ISA SADI: document review should also resolve this discrepancy. |
| RES-2022-2320 | OP serial blank in PRA 119837, linked Transfer of Title 221493 and OSS 37549. Candidate PRA 175664 / TEMP-90350 has serial 11017, KAWU BALA, KUYAN TA INNA and TP/K/338B. Candidate has no plot number, no explicit linkage and a different property ID; confirm against the OP for plot 3296 before applying. |
| RES-2025-6064 | OP serial blank in PRA 119796, linked Transfer of Title 221970 and OSS 37594. No corroborated serial found. Serial 918 belongs to RES-2025-6065 / plot 4766, also NUHU AMADU, and must not be copied to this file / plot 4765. |
| IND-2026-209 | OSS 37374 was auto-created by commissioning 49598, without source PRA/instrument linkage. No OP or Transfer of Title found under this file. Indexing 198964 contains MUHAMMAD AUWAL MUSA AFAMAI for both original/current holder, but FileIndexingService::createFromMlsFileNumber defaults both to the commissioned applicant. This is not independent evidence of previous ownership. |

Checked database exact file/temp aliases, file indexing, commissioning, PRA, instrument capture, verification/matching records, history, available backup tables, and candidate holders/plots. No matching entries found in oss_change_of_ownership or oss_verifications. The Applications original-holder display resolves OP/source/transfer parties; IND-2026-209 has none, explaining the blank.

Evidence: ownership-missing-fields-20261004.json, ownership-expanded-evidence.json, ownership-candidate-evidence.json and ownership-oss-evidence.json in this directory. Candidate matches are investigative leads, not approved repairs. Physical OP cards or independently verified source records are needed to settle unresolved values.
