<?php
include("checksession.php");
require_once __DIR__ . '/../shared/AgreementService.php';
include("config.php");
error_reporting(0);

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$cp_id = (int) $Login_user_IDvl;

$stmtCp = $db_conn->prepare("SELECT * FROM channel_partners WHERE id = ?");
$stmtCp->bind_param('i', $cp_id);
$stmtCp->execute();
$cp = $stmtCp->get_result()->fetch_assoc();
$stmtCp->close();

$settings  = get_agreement_settings($db_conn);
$agreement = get_or_create_cp_agreement($db_conn, $cp_id);
$isLocked  = (int) ($agreement['is_locked'] ?? 0) === 1;
// See territory-partner/agreement.php's identical $scheduleChanged for why.
$scheduleChanged = agreement_needs_resign($agreement);

if (isset($_SESSION['sucMessage'])) { $flashMsg = $_SESSION['sucMessage']; unset($_SESSION['sucMessage']); }
if (isset($_SESSION['errorMessage'])) { $flashErr = $_SESSION['errorMessage']; unset($_SESSION['errorMessage']); }

function fv($val) { return htmlspecialchars((string) ($val ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Channel Partner Agreement : <?php echo fv($business_name ?? 'Femi9'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .agr-doc { max-width:820px; margin:0 auto; background:#fff; padding:36px 44px; border:1px solid #e5e7eb; border-radius:10px; font-size:13.5px; line-height:1.6; color:#1f2937; }
        .agr-doc h1 { font-size:18px; text-align:center; margin-bottom:20px; }
        .agr-doc h2 { font-size:14.5px; margin:22px 0 8px; }
        .agr-field-row { display:flex; align-items:center; gap:8px; margin:10px 0; flex-wrap:wrap; }
        .agr-field-row label { font-weight:600; min-width:150px; }
        .agr-field-row input, .agr-field-row .agr-value { flex:1; min-width:180px; border:none; border-bottom:1px solid #9ca3af; padding:2px 4px; background:transparent; }
        .agr-value { color:#374151; }
        .agr-value.agr-blank { color:#9ca3af; font-style:italic; }
        .agr-ul { margin:8px 0 8px 20px; }
        .agr-witness-box { border:1px solid #e5e7eb; border-radius:10px; padding:14px 16px; margin-top:14px; }
        .agr-sig-block { margin-top:14px; padding-top:10px; border-top:1px dashed #e5e7eb; }
        .agr-sig-img { max-width:200px; max-height:44px; object-fit:contain; border-bottom:1px solid #9ca3af; }
        @media print { .no-print { display:none !important; } .agr-doc { border:none; } }
        .agr-locked-banner { background:#f0fdf4; border:1px solid #bbf7d0; color:#065f46; border-radius:10px; padding:10px 14px; margin-bottom:16px; font-size:13px; }
        .agr-schedule-changed { background:#fff9db; border:1px solid #ffe066; border-radius:8px; padding:12px 16px; margin:10px 0; }
        .agr-schedule-changed .agr-field-row { margin:8px 0; }
        @media (max-width: 575.98px) {
            .agr-doc { padding:18px 16px; font-size:13px; }
            .agr-field-row { flex-direction:column; align-items:flex-start; gap:2px; }
            .agr-field-row label { min-width:0; }
            .agr-field-row input, .agr-field-row .agr-value { width:100%; min-width:0; }
            .sig-draw-pane canvas, .sig-type-pane canvas { max-width:100%; }
        }
    </style>
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar no-print">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">
                    <?php if (!empty($flashMsg)): ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:'success', title:'Success', text:<?php echo json_encode($flashMsg); ?>});</script>
                    <?php endif; ?>
                    <?php if (!empty($flashErr)): ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:'error', title:'Error', text:<?php echo json_encode($flashErr); ?>});</script>
                    <?php endif; ?>

                    <div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                        <h1 style="font-size:20px;font-weight:600;color:#1f2937;margin:0;">Channel Partner Agreement</h1>
                        <?php if ($isLocked): ?>
                        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Print / Save as PDF</button>
                        <?php endif; ?>
                    </div>

                    <?php if ($isLocked): ?>
                    <div class="agr-locked-banner no-print">
                        This agreement was signed and submitted on <?php echo fv(date('d-M-Y h:i A', strtotime($agreement['signed_at']))); ?> and is now read-only.
                    </div>
                    <?php endif; ?>

                    <form method="post" action="agreement-action.php" id="agreementForm" onsubmit="return prepareSignaturesAndValidate();">
                    <input type="hidden" name="csrf_token" value="<?php echo fv($_SESSION['csrf_token']); ?>">
                    <div class="agr-doc">
                        <h1>FEMI9 LLP DIVISION CHANNEL PARTNER AGREEMENT</h1>

                        <p>This Division Channel Partner Agreement (hereinafter referred to as the "Agreement") is made and executed on the date and at the place specified below and shall become effective from the Effective Date recorded in Schedule&nbsp;&ndash;&nbsp;1.</p>

                        <div class="agr-field-row"><label>DATE:</label>
                            <?php if ($isLocked): ?><span class="agr-value"><?php echo fv(date('d-m-Y', strtotime($agreement['agreement_date'] ?? 'now'))); ?></span>
                            <?php else: ?><input type="date" name="agreement_date" value="<?php echo fv($agreement['agreement_date'] ?: date('Y-m-d')); ?>" required>
                            <?php endif; ?>
                        </div>
                        <div class="agr-field-row"><label>PLACE:</label>
                            <?php if ($isLocked): ?><span class="agr-value"><?php echo fv($agreement['agreement_place']); ?></span>
                            <?php else: ?><input type="text" name="agreement_place" value="<?php echo fv($agreement['agreement_place']); ?>" required>
                            <?php endif; ?>
                        </div>
                        <div class="agr-field-row"><label>Applicable Security Deposit (Initial):</label>
                            <span class="agr-value<?php echo $agreement['security_deposit'] === null ? ' agr-blank' : ''; ?>">
                                <?php echo $agreement['security_deposit'] !== null ? 'Rs. ' . fv(number_format((float)$agreement['security_deposit'], 2)) : 'To be set by Company'; ?>
                            </span>
                        </div>
                        <p class="text-muted" style="font-size:12px;margin-top:-6px;">(subject to review under clause 5 and schedule 1)</p>
                        <div class="agr-field-row"><label>Mode of Deposit:</label>
                            <?php if ($isLocked): ?><span class="agr-value"><?php echo fv($agreement['deposit_mode']); ?></span>
                            <?php else: ?><input type="text" name="deposit_mode" value="<?php echo fv($agreement['deposit_mode']); ?>">
                            <?php endif; ?>
                        </div>
                        <div class="agr-field-row"><label>Transaction / Receipt / Reference No.:</label>
                            <?php if ($isLocked): ?><span class="agr-value"><?php echo fv($agreement['deposit_txn_ref']); ?></span>
                            <?php else: ?><input type="text" name="deposit_txn_ref" value="<?php echo fv($agreement['deposit_txn_ref']); ?>">
                            <?php endif; ?>
                        </div>

                        <h2>BETWEEN</h2>
                        <p><strong>FEMI9 LLP</strong>, a Limited Liability Partnership duly incorporated under the provisions of the Limited Liability Partnership Act, 2008, having its Registered Office / Principal Place of Business at:</p>
                        <p class="agr-value<?php echo empty($settings['company_address']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($settings['company_address'] ?: 'To be filled by Company')); ?></p>
                        <p>(hereinafter referred to as "FEMI9" or the "Company", which expression shall, unless repugnant to the context or meaning thereof, include its successors, legal representatives, permitted assigns and administrators);</p>

                        <h2>AND</h2>
                        <p><strong>CHANNEL PARTNER</strong></p>
                        <div class="agr-field-row"><label>Name:</label><span class="agr-value"><?php echo fv($cp['name']); ?></span></div>
                        <div class="agr-field-row"><label>Business / Entity Name:</label><span class="agr-value<?php echo empty($cp['company_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($cp['company_name'] ?: 'Not applicable'); ?></span></div>
                        <div class="agr-field-row"><label>Address:</label><span class="agr-value"><?php echo fv($cp['address']); ?></span></div>
                        <div class="agr-field-row"><label>Mobile Number:</label><span class="agr-value"><?php echo fv($cp['mobile']); ?></span></div>
                        <div class="agr-field-row"><label>Email Address:</label><span class="agr-value<?php echo empty($cp['email']) ? ' agr-blank' : ''; ?>"><?php echo fv($cp['email'] ?: '—'); ?></span></div>
                        <p>(hereinafter referred to as the "Channel Partner" or "CP", which expression shall, unless repugnant to the context or meaning thereof, include, where applicable, its successors, legal representatives, heirs, executors, administrators and permitted assigns).</p>
                        <p>FEMI9 and the Channel Partner are hereinafter individually referred to as a "Party" and collectively as the "Parties."</p>

                        <h2>1. APPOINTMENT &amp; DIVISION ALLOCATION</h2>
                        <p>FEMI9 hereby appoints the Channel Partner as an authorized Division Channel Partner to establish, operate and manage the Company's approved warehouse and stock-point functions for the Division(s) officially allotted under this Agreement.</p>
                        <p>The primary responsibility of the Channel Partner shall be to receive, securely store, maintain proper custody of and facilitate the authorized handover of Company Stock to eligible Territory Partners operating within the respective allotted Division(s).</p>
                        <p>A single Master Agreement may cover one or more Divisions allotted to the Channel Partner by FEMI9. Notwithstanding execution under a single Agreement, each allotted Division shall maintain its own separate operational, commercial and financial identity, including, but not limited to, the following:</p>
                        <ul class="agr-ul"><li>Division Allocation;</li><li>Applicable Security Deposit;</li><li>Approved Warehouse;</li><li>Stock Accountability;</li><li>Verified Eligible Division Turnover; and</li><li>Commercial Settlement.</li></ul>
                        <p>The particulars applicable to each allotted Division shall be recorded in Schedule&nbsp;&ndash;&nbsp;1, which shall form an integral part of this Agreement.</p>

                        <h2>2. CORE ROLE &amp; RESPONSIBILITIES</h2>
                        <p>The Channel Partner shall perform its responsibilities with due care, diligence and professionalism and shall ensure the efficient management of the warehouse and inventory operations entrusted by FEMI9. Without limitation, the Channel Partner shall:</p>
                        <ul class="agr-ul">
                            <li>Establish, maintain and operate the Approved Warehouse for each allotted Division;</li>
                            <li>Receive, securely store and safeguard Company Stock entrusted to its custody;</li>
                            <li>Maintain proper inventory control and stock discipline at all times;</li>
                            <li>Facilitate timely and authorized stock handover to eligible Territory Partners strictly in accordance with the Company's approved operational process;</li>
                            <li>Maintain adequate warehouse manpower, equipment and operational infrastructure;</li>
                            <li>Comply with all authorized stock management, documentation and system procedures prescribed by FEMI9;</li>
                            <li>Maintain accurate Division-wise stock accountability and inventory records;</li>
                            <li>Cooperate fully with Company inspections, stock verification, audits and reconciliation processes;</li>
                            <li>Protect Company Stock, assets and records under its custody against loss, misuse or damage; and</li>
                            <li>Ensure uninterrupted, safe and efficient warehouse operations at all times.</li>
                        </ul>
                        <p>The Channel Partner shall not release, transfer, divert, pledge, sell or otherwise deal with Company Stock except through the authorized processes established by FEMI9.</p>

                        <h2>3. SECURITY DEPOSIT</h2>
                        <p>The Channel Partner shall maintain the Security Deposit applicable to each allotted Division as specified in Schedule&nbsp;&ndash;&nbsp;1. The Security Deposit shall constitute a refundable business security maintained in support of the Company's authorized inventory and operational framework and shall not be construed as consideration for ownership, investment or acquisition of any proprietary rights. Accordingly, the Security Deposit shall:</p>
                        <ul class="agr-ul">
                            <li>Be specific to the respective Division;</li>
                            <li>Remain refundable subject to the terms and conditions of this Agreement;</li>
                            <li>Be independent of business turnover;</li>
                            <li>Be separate from Territory Partner purchase payments;</li>
                            <li>Not constitute the purchase price of a division;</li>
                            <li>Not confer any equity participation or ownership interest in FEMI9;</li>
                            <li>Not represent a franchise acquisition fee; and</li>
                            <li>Not create any ownership or proprietary right over any geographical territory.</li>
                        </ul>
                        <p>Unless otherwise agreed in writing, the applicable Security Deposit shall ordinarily be paid once for each allotted Division and shall support the authorized stock capacity and operational framework approved by FEMI9. Where multiple Divisions are allotted under this Agreement, the Security Deposit may be maintained or recorded as a consolidated amount for administrative convenience; however, the applicable Security Deposit attributable to each individual Division shall continue to be separately identified and maintained in Schedule&nbsp;&ndash;&nbsp;1.</p>

                        <h2>4. COMPANY STOCK &amp; REPLENISHMENT</h2>
                        <p>FEMI9 may place Company Stock at the Approved Warehouse in accordance with the applicable Security Deposit, approved inventory capacity and the Company's operational stock planning. All Company Stock placed at the Approved Warehouse shall remain the exclusive property of FEMI9 and shall continue to be governed by the Company's ownership, inventory, accounting and billing framework until released through an authorized transaction. The Channel Partner shall act solely as the custodian of such Company Stock and shall not acquire any ownership or proprietary interest merely by virtue of physical possession or storage of the stock. As authorized stock is released to eligible Territory Partners, FEMI9 may replenish inventory at the Approved Warehouse based upon:</p>
                        <ul class="agr-ul"><li>Verified Stock Movement;</li><li>Available Inventory Position;</li><li>Business Requirements;</li><li>Applicable Security Deposit Capacity; and</li><li>Company Inventory Planning.</li></ul>
                        <p>Routine replenishment of Company Stock shall not require repeated payment of the applicable Security Deposit. The total stock maintained at the Approved Warehouse shall ordinarily remain within the inventory capacity approved by FEMI9 for the respective Division unless otherwise authorized in writing.</p>

                        <h2>5. SECURITY DEPOSIT REVIEW &amp; ENHANCEMENT</h2>
                        <p>FEMI9 may periodically review the adequacy of the Security Deposit maintained for each allotted Division to ensure that it continues to support the operational stock requirements and sustained business growth of that Division. An isolated or temporary increase in business turnover shall not, by itself, require an immediate enhancement of the applicable Security Deposit. However, where sustained business performance and stock movement over a reasonable review period demonstrate the need for materially higher inventory capacity, FEMI9 may require an appropriate enhancement of the applicable Division Security Deposit. Any requirement for enhancement shall be communicated through the Company's authorized communication process together with a reasonable period for compliance. Where the Channel Partner fails to provide the required enhancement within the communicated period, FEMI9 may, depending upon the operational requirements of the Division: review the applicable Division allocation; restrict inventory capacity; suspend further stock placement; or reduce, discontinue or reallocate the affected Division. An enhancement applicable to one Division shall not automatically increase or otherwise affect the Security Deposit applicable to any other allotted Division unless separately reviewed and approved by FEMI9.</p>

                        <h2>6. COMMERCIAL COMMITMENT &amp; MONTHLY BUSINESS RETURN</h2>
                        <p>For each Eligible Active Division, the Channel Partner shall be entitled to receive a Monthly Business Return calculated based on whichever of the following amounts is higher: (A) Two Percent (2%) of the applicable Approved Division Security Deposit; OR (B) Six Percent (6%) of the Verified Eligible Division Turnover. The above two calculations are mutually exclusive and shall not be aggregated or added together for the purpose of determining the Monthly Business Return.</p>
                        <p>For the purposes of this Agreement, "Verified Eligible Division Turnover" means the value of eligible product purchases directly paid or remitted to FEMI9 by Eligible Territory Partners mapped to the respective Division and duly verified in the Company's authorized business system. No other sales, transfers, deposits, unverified transactions or unrelated business values shall automatically be included in the calculation of the Verified Eligible Division Turnover. Accordingly, where the amount calculated at Six Percent (6%) of the Verified Eligible Division Turnover is lower than the amount calculated at Two Percent (2%) of the applicable Approved Division Security Deposit, the Security Deposit-based calculation shall apply; where higher, the Turnover-based calculation shall apply. The Monthly Business Return shall be calculated separately for each Eligible Active Division. Applicable statutory deductions, including Tax Deducted at Source (TDS), where legally required, may be deducted by FEMI9. The Monthly Business Return, if payable, shall be released by FEMI9 in accordance with the Company's authorized commercial settlement process and applicable commercial policy after completion of the monthly verification and reconciliation process. The applicable Goods and Services Tax (GST) treatment, if any, shall be governed by the prevailing applicable law and the Company's authorized accounting framework.</p>

                        <h2>7. MONTHLY BUSINESS STATEMENT &amp; RECONCILIATION</h2>
                        <p>FEMI9 shall prepare and issue a Monthly Business Statement for each Eligible Active Division on or before the 5th day of the succeeding calendar month or within such reasonable period as may be required under the Company's operational processes. The Monthly Business Statement may include, where applicable: Opening Stock; Closing Stock; Stock Received; Stock Issued; Verified Eligible Division Turnover; Applicable Commercial Calculation; Monthly Business Return Payable; Previous Balance Adjustments; Reconciliation Entries; and other relevant operational or commercial particulars. The Channel Partner shall verify the Monthly Business Statement and promptly notify the Company of any discrepancy supported by reasonable documentary evidence. In the absence of any written objection within the period specified by FEMI9, the Monthly Business Statement may be treated as accepted for operational purposes. Any genuine discrepancy identified during subsequent reconciliation may be corrected by the Company through appropriate adjustment in a future statement.</p>

                        <h2>8. APPROVED WAREHOUSE</h2>
                        <p>Each Division shall ordinarily operate through its separately approved warehouse. A warehouse approved for one Division shall not automatically be used for another Division. Any exception, including the use of one physical location for more than one Division, shall require the prior written approval of FEMI9 and shall be subject to such Division-wise stock segregation, system control and operational accountability as FEMI9 may prescribe. The Approved Warehouse particulars for each Division shall be recorded in Schedule&nbsp;&ndash;&nbsp;1. The Channel Partner shall not relocate Company Stock to an unauthorized warehouse or location without the prior written approval of FEMI9, except where temporary relocation is reasonably necessary to safeguard Company Stock in an emergency, provided that FEMI9 is informed without undue delay and the stock is returned to an Approved Warehouse or such other location as may be authorized by FEMI9. The Approved Warehouse shall be maintained and operated by the Channel Partner in accordance with the operational standards, warehouse procedures and stock management requirements prescribed by FEMI9 from time to time. The Channel Partner shall maintain proper warehouse records, ensure Division-wise stock accountability, preserve accurate inventory records and extend all reasonable cooperation during inspections, audits, stock verification and other authorized operational reviews conducted by FEMI9 or its authorized representatives. The Approved Warehouse shall be used solely for the authorized business operations of the respective Division unless otherwise expressly approved in writing by FEMI9.</p>

                        <h2>9. WAREHOUSE MANAGEMENT, SAFETY &amp; OPERATIONAL RESPONSIBILITIES</h2>
                        <p>The Channel Partner shall maintain the Approved Warehouse in a safe, secure, clean and operational condition suitable for the storage and handling of Company Stock, and shall be responsible for ensuring that the warehouse is managed in accordance with applicable laws, regulatory requirements and the Company's operational standards. Without limitation, the Channel Partner shall: maintain proper warehouse security and controlled access; protect Company Stock against theft, loss, misuse, contamination or damage; maintain adequate insurance coverage for the Approved Warehouse and Company Stock, where applicable (the Channel Partner shall be solely responsible for any dispute, claim, loss or liability relating to the Approved Warehouse, and FEMI9 shall bear no responsibility in connection therewith); ensure proper storage conditions appropriate to the nature of the products; maintain adequate manpower for warehouse operations; ensure cleanliness, hygiene and orderly stock arrangement; maintain all stock registers, inventory records and system entries accurately; cooperate with stock verification, inspection and audit conducted by the Company; promptly report any shortage, damage, discrepancy or unusual incident affecting Company Stock; and comply with all warehouse operating procedures communicated by FEMI9 from time to time. Where applicable, the Channel Partner shall obtain and maintain all licenses, approvals, registrations and statutory compliances necessary for lawful warehouse operations. Failure to maintain the Approved Warehouse in accordance with the Company's standards may constitute a material operational deficiency under this Agreement.</p>

                        <h2>10. STOCK HANDOVER, LOADING, DELIVERY &amp; CUSTODY</h2>
                        <p>The Channel Partner shall release Company Stock only to authorized Territory Partners or other persons specifically authorized by FEMI9 through the Company's approved business process. All stock movements shall be supported by the prescribed documentation and recorded through the Company's authorized inventory management system. The Channel Partner shall ensure that stock is handed over only upon proper authorisation; loading and unloading operations are carried out safely and efficiently; quantity, batch details and product particulars are verified before release; stock movement records are accurately maintained; delivery acknowledgements are obtained wherever applicable; and no unauthorized stock movement takes place. Until the authorized handover of Company Stock is completed in accordance with the Company's approved procedures, the Channel Partner shall remain responsible for the safe custody, protection and accountability of such stock. The Channel Partner shall not sell, transfer, pledge, hypothecate, dispose of or otherwise deal with Company Stock except strictly in accordance with the written authorisation and operational procedures prescribed by FEMI9. Any loss, shortage or unauthorized movement of Company Stock attributable to negligence, misconduct or failure to exercise reasonable care by the Channel Partner may result in appropriate recovery, corrective action or other remedies available to FEMI9 under this Agreement and applicable law.</p>

                        <h2>11. DIVISION-WISE OPERATIONS &amp; STOCK ACCOUNTABILITY</h2>
                        <p>Where the Channel Partner is allotted more than one Division under this Agreement, each Division shall be operated independently for operational, commercial and accounting purposes. The Channel Partner shall maintain separate and accurate records for each allotted Division, including but not limited to: Security Deposit; Stock Receipt; Stock Availability; Stock Issue and Handover; Verified Eligible Division Turnover; Commercial Calculations; Monthly Business Return; Inventory Reconciliation; and other operational records as may be prescribed by FEMI9 from time to time. The Channel Partner shall ensure that stock, records and commercial transactions relating to one Division are not mixed, transferred or adjusted against another Division without the prior written approval of FEMI9. Each Division shall remain independently accountable for its operational performance, inventory management and commercial settlement.</p>

                        <h2>12. AUDIT, INSPECTION &amp; STOCK VERIFICATION</h2>
                        <p>FEMI9 or its authorized representatives shall have the right to conduct periodic or surprise inspections, audits and stock verification of the Approved Warehouse and related business records for the purpose of ensuring operational accuracy, inventory accountability and compliance with this Agreement. The Channel Partner shall extend full cooperation during such inspections and shall provide access to: Warehouse Premises; Company Stock; Inventory Records; Physical Stock Registers; Digital Inventory Systems; Commercial Statements; Purchase and Delivery Records; and any other documents reasonably required for verification. Where any discrepancy, shortage, excess stock or operational irregularity is identified during inspection or audit, the Channel Partner shall cooperate with the Company in carrying out immediate reconciliation and corrective action. Repeated failure to maintain proper records or refusal to cooperate with authorized inspections may constitute a material breach of this Agreement.</p>

                        <h2>13. COMPANY POLICIES &amp; OPERATIONAL COMPLIANCE</h2>
                        <p>The Channel Partner shall comply with all applicable operational policies, Standard Operating Procedures (SOPs), warehouse guidelines, inventory management procedures, commercial policies and authorized business instructions issued by FEMI9 from time to time. FEMI9 reserves the right to introduce, modify or update its operational procedures where reasonably required for Business Expansion; Technology Implementation; Inventory Optimization; Distribution Efficiency; Consumer Protection; Regulatory Compliance; and Improvement of Operational Standards. The Channel Partner shall implement such operational updates within the period reasonably communicated by the Company. No operational circular, administrative instruction or internal guideline shall retrospectively alter any valid commercial entitlement already accrued under this Agreement unless required by law or expressly agreed between the Parties.</p>

                        <h2>14. BRAND PROTECTION &amp; INTELLECTUAL PROPERTY</h2>
                        <p>The Channel Partner acknowledges that all trademarks, trade names, logos, product names, packaging designs, copyrights, promotional materials and other intellectual property associated with FEMI9 are and shall remain the exclusive property of the Company or its lawful owner. The Channel Partner shall use the Company's brand assets solely for authorized business purposes and strictly in accordance with the Company's branding guidelines. The Channel Partner shall not, directly or indirectly: alter or modify the Company's trademarks or branding; create or distribute unauthorized promotional materials; make false or misleading representations regarding FEMI9 products; use the Company's intellectual property beyond the authority granted under this Agreement; register or attempt to register any trademark, trade name or domain name similar to that of FEMI9; or engage in any activity likely to damage the goodwill, reputation or commercial interests of the Company. Upon expiry or termination of this Agreement, all rights granted to the Channel Partner to use the Company's intellectual property shall immediately cease unless otherwise authorized by FEMI9 in writing.</p>

                        <h2>15. CONFIDENTIALITY &amp; BUSINESS INFORMATION</h2>
                        <p>The Channel Partner acknowledges that, during the course of its business relationship with FEMI9, it may receive access to confidential, proprietary and commercially sensitive information relating to the Company's business operations, and shall maintain the confidentiality of all such information, using the same solely for the purpose of performing its obligations under this Agreement. Confidential Information shall include, without limitation: Pricing Structures; Commercial Policies; Security Deposit Information; Monthly Business Return Calculations; Warehouse Operations; Inventory Data; Customer and Territory Partner Information; Business Plans and Expansion Strategies; Internal Reports and Financial Information; Software, Systems and Operational Processes; and any other information which is confidential by its nature or expressly designated as confidential by FEMI9. The Channel Partner shall not disclose, copy, reproduce, distribute or otherwise make available any Confidential Information to any third party without the prior written consent of FEMI9, except where such disclosure is required under applicable law or by a competent legal authority. The obligations contained in this Clause shall survive the expiry, voluntary exit or termination of this Agreement and shall remain binding upon the Channel Partner for so long as the information remains confidential.</p>

                        <h2>16. INDEPENDENT BUSINESS RELATIONSHIP</h2>
                        <p>The Channel Partner is appointed solely as an independent business partner of FEMI9 and shall conduct its operations independently and at its own cost, risk and responsibility, subject to the terms and conditions of this Agreement. Nothing contained in this Agreement shall be construed as creating or implying: an employer&ndash;employee relationship; a partnership, joint venture or association between the Parties; any agency relationship with unrestricted authority; any equity participation or ownership interest in FEMI9; any franchise ownership or proprietary right over the Company's business, products or allotted Division(s); or any right to bind, commit or legally obligate FEMI9 except to the extent expressly authorized by the Company in writing. The Channel Partner shall remain solely responsible for its employees and representatives; business licenses and statutory registrations; labor law compliance; tax obligations; insurance, where applicable; operational expenses; and all liabilities arising out of its independent business operations. Nothing contained in this Agreement shall restrict FEMI9 from appointing other authorized business partners for territories, divisions, products or business channels as may be required for its commercial operations.</p>

                        <h2>17. NON-PERFORMANCE &amp; CORRECTIVE ACTION</h2>
                        <p>Where the Channel Partner materially fails to perform any obligation under this Agreement or repeatedly fails to comply with the Company's operational requirements, FEMI9 may notify the Channel Partner of such deficiency and require appropriate corrective action within a reasonable period. Corrective action may relate to, without limitation: failure to maintain the Approved Warehouse; poor inventory management; repeated stock discrepancies; failure to maintain prescribed records; delay in stock handover; failure to comply with Company SOPs; inadequate manpower or warehouse infrastructure; persistent operational deficiencies; failure to cooperate during audit or inspection; or any other material non-compliance affecting the Company's business operations. Where the Channel Partner fails to satisfactorily rectify the identified deficiencies within the stipulated period, FEMI9 may, depending upon the nature and seriousness of the default: issue written warnings; restrict warehouse operations; suspend further stock placement; suspend or withhold applicable commercial benefits; review the Division allocation; reduce or reallocate one or more allotted Divisions; or terminate this Agreement. The exercise of corrective action by FEMI9 shall be without prejudice to any other contractual, statutory or legal remedies available to the Company.</p>

                        <h2>18. SERIOUS BREACH &amp; IMMEDIATE ACTION</h2>
                        <p>Notwithstanding any other provision contained herein, FEMI9 reserves the right to take immediate protective action, including suspension or termination of this Agreement, where the Channel Partner commits any act which, in the reasonable opinion of the Company, constitutes a serious breach or poses an immediate risk to the Company's business operations, assets, consumers or reputation. Such serious breaches may include, without limitation: fraud or dishonest conduct; theft or misappropriation of Company Stock, funds or assets; deliberate stock manipulation; financial irregularities; falsification of warehouse or inventory records; unauthorized diversion or sale of Company Stock; serious misuse of Company trademarks or branding; unauthorized system access or data manipulation; willful misconduct causing substantial financial or reputational loss; material violation of applicable laws or statutory requirements; or any other act likely to seriously prejudice the legitimate interests of FEMI9. Where immediate intervention is reasonably necessary to safeguard the Company's interests, FEMI9 shall not be required to provide a prior corrective period before taking appropriate action. Nothing contained herein shall prejudice the Company's right to initiate appropriate civil, criminal or statutory proceedings wherever applicable.</p>

                        <h2>19. TERM, VOLUNTARY EXIT &amp; TERMINATION</h2>
                        <p>This Agreement shall commence on the Effective Date specified in Schedule&nbsp;&ndash;&nbsp;1 and shall continue in force unless terminated in accordance with the provisions of this Agreement. The Channel Partner may voluntarily discontinue its appointment by providing not less than Thirty (30) Days' prior written notice to FEMI9 unless otherwise mutually agreed in writing. Where the Channel Partner has been appointed for more than one Approved Division under this Agreement, the Channel Partner may, with the prior written approval of FEMI9, voluntarily discontinue one or more specific Division(s) without terminating this Agreement in its entirety; in such event, the exiting Division(s) shall be subject to independent stock, commercial and operational reconciliation, while the remaining Approved Division(s) shall continue without interruption, subject to continued compliance with this Agreement. The applicable refundable Security Deposit and commercial settlement relating to the exiting Division(s) shall be processed in accordance with this Agreement after adjustment of all lawful dues and completion of reconciliation. During the applicable notice period, the Channel Partner shall complete reconciliation of all outstanding commercial transactions; return Company property, records, systems, documents and promotional materials, where applicable; complete stock reconciliation; cooperate in business transition and operational handover; submit all pending reports and warehouse records; and fulfil all other reasonable obligations communicated by FEMI9. FEMI9 may terminate this Agreement where the Channel Partner commits a Material Breach; operational deficiencies remain unresolved despite reasonable corrective opportunities; the Channel Partner commits any serious breach referred to in Clause 18; the Channel Partner ceases business operations or becomes insolvent; or continuation of the appointment is reasonably considered detrimental to the Company's legitimate commercial interests. Termination shall be without prejudice to any rights, obligations or liabilities accrued prior to the effective date of termination.</p>

                        <h2>20. EFFECT OF EXIT OR TERMINATION</h2>
                        <p>Upon the effective date of exit or termination of this Agreement: all authority granted to the Channel Partner under this Agreement shall immediately cease; the Channel Partner shall discontinue all use of the Company's name, trademarks, logos, promotional materials and other intellectual property; Company Stock, records, documents, systems and assets, wherever applicable, shall be reconciled and dealt with in accordance with the Company's authorized procedures; outstanding commercial accounts shall be reconciled; the applicable refundable Security Deposit shall be processed in accordance with this Agreement, subject to completion of stock and commercial reconciliation, adjustment of all lawful dues and fulfilment of all contractual obligations (and shall ordinarily be refunded by FEMI9 within Ninety (90) Days from the date of completion of such reconciliation); system access and operational authorizations may be withdrawn by FEMI9; and all continuing obligations intended by their nature to survive termination, including confidentiality and intellectual property obligations, shall remain binding upon the Channel Partner.</p>

                        <h2>21. GOVERNING LAW, JURISDICTION &amp; ENTIRE AGREEMENT</h2>
                        <p>This Agreement shall be governed by and construed in accordance with the laws of the Republic of India. The Parties shall first endeavor to resolve any dispute, controversy or claim arising out of or relating to this Agreement through mutual discussions and good faith negotiations. If the dispute remains unresolved, the Parties agree that the competent courts situated in <strong>ERODE DISTRICT, TAMIL NADU</strong> shall have exclusive jurisdiction over all matters arising out of or relating to this Agreement. This Agreement, together with Schedule&nbsp;&ndash;&nbsp;1, every duly executed written amendment and the Company's applicable commercial framework expressly incorporated herein, constitutes the complete and entire agreement between the Parties, and supersedes all prior discussions, negotiations, representations and understandings relating to the subject matter hereof, whether oral or written. Operational policies, SOPs, administrative circulars and business guidelines issued by FEMI9 shall facilitate implementation of this Agreement but shall not override or amend any express contractual provision unless incorporated through an authorized written amendment executed by the Parties. If any provision of this Agreement is declared invalid or unenforceable by a court of competent jurisdiction, the remaining provisions shall continue in full force and effect to the maximum extent permitted by law.</p>

                        <h2>SCHEDULE &ndash; 1: DIVISION ALLOCATION &amp; COMMERCIAL PARTICULARS</h2>
                        <p>The particulars contained in this Schedule shall form an integral part of this Division Channel Partner Agreement and shall be read together with all the terms and conditions contained herein.</p>
                        <div class="agr-field-row"><label>Channel Partner Name:</label><span class="agr-value"><?php echo fv($cp['name']); ?></span></div>
                        <div class="agr-field-row"><label>Channel Partner ID / Code:</label><span class="agr-value"><?php echo fv($cp['cp_id']); ?></span></div>
                        <div class="agr-field-row"><label>Business / Entity Name:</label><span class="agr-value<?php echo empty($cp['company_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($cp['company_name'] ?: 'Not applicable'); ?></span></div>
                        <div class="agr-field-row"><label>State:</label><span class="agr-value<?php echo empty($cp['branch_state']) ? ' agr-blank' : ''; ?>"><?php echo fv($cp['branch_state'] ?: '—'); ?></span></div>
                        <div class="agr-field-row"><label>District:</label><span class="agr-value<?php echo empty($cp['branch_district']) ? ' agr-blank' : ''; ?>"><?php echo fv($cp['branch_district'] ?: '—'); ?></span></div>
                        <?php if ($scheduleChanged): ?>
                        <p style="color:#92600a;font-size:12.5px;font-weight:600;margin:14px 0 2px;">&#9998; Updated by Company since your last signature &mdash; please review before signing again:</p>
                        <div class="agr-schedule-changed">
                        <?php endif; ?>
                        <div class="agr-field-row"><label>Approved Division(s):</label><span class="agr-value<?php echo empty($agreement['approved_divisions']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['approved_divisions'] ?: 'To be set by Company')); ?></span></div>
                        <div class="agr-field-row"><label>Division Code(s):</label><span class="agr-value<?php echo empty($agreement['division_codes']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['division_codes'] ?: '—')); ?></span></div>
                        <div class="agr-field-row"><label>Approved Warehouse Address:</label><span class="agr-value<?php echo empty($agreement['approved_warehouse_address']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['approved_warehouse_address'] ?: 'To be set by Company')); ?></span></div>
                        <div class="agr-field-row"><label>Applicable Security Deposit:</label><span class="agr-value<?php echo $agreement['security_deposit'] === null ? ' agr-blank' : ''; ?>"><?php echo $agreement['security_deposit'] !== null ? 'Rs. ' . fv(number_format((float)$agreement['security_deposit'], 2)) : 'To be set by Company'; ?></span></div>
                        <div class="agr-field-row"><label>Approved Stock Holding Capacity:</label><span class="agr-value<?php echo empty($agreement['stock_holding_capacity']) ? ' agr-blank' : ''; ?>"><?php echo fv($agreement['stock_holding_capacity'] ?: 'To be set by Company'); ?></span></div>
                        <div class="agr-field-row"><label>Applicable Commercial / Purchase Category:</label><span class="agr-value<?php echo empty($agreement['commercial_category']) ? ' agr-blank' : ''; ?>"><?php echo fv($agreement['commercial_category'] ?: 'To be set by Company'); ?></span></div>
                        <div class="agr-field-row"><label>Effective Date:</label><span class="agr-value<?php echo empty($agreement['effective_date']) ? ' agr-blank' : ''; ?>"><?php echo $agreement['effective_date'] ? fv(date('d-m-Y', strtotime($agreement['effective_date']))) : 'To be set by Company'; ?></span></div>
                        <div class="agr-field-row"><label>Other Approved Commercial Particulars:</label><span class="agr-value<?php echo empty($agreement['other_particulars']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['other_particulars'] ?: '—')); ?></span></div>
                        <?php if ($scheduleChanged): ?>
                        </div>
                        <?php endif; ?>
                        <div class="agr-field-row"><label>Monthly Business Return:</label><span class="agr-value">As per Clause 6 of this Agreement and the Company's prevailing Commercial Commitment Policy.</span></div>
                        <p>The above particulars shall remain valid subject to the Channel Partner's continued compliance with this Agreement, satisfactory operational performance and ongoing approval by FEMI9. Any modification to the above particulars shall be effective only upon written approval issued through the Company's authorized communication process.</p>

                        <h2>FINAL DECLARATION &amp; ACCEPTANCE</h2>
                        <p>The Channel Partner hereby declares, confirms and agrees that:</p>
                        <ul class="agr-ul">
                            <li>This Agreement has been carefully read, fully understood and voluntarily accepted.</li>
                            <li>The operational responsibilities relating to warehouse management, inventory accountability and Division operations have been clearly explained and are fully understood.</li>
                            <li>The Security Deposit applicable to each allotted Division is maintained solely as a refundable business security in accordance with the terms of this Agreement.</li>
                            <li>The Monthly Business Return, eligibility criteria and commercial commitment framework have been fully understood and accepted.</li>
                            <li>Separate operational and commercial accountability shall be maintained for each allotted Division covered under this Agreement.</li>
                            <li>Company Stock shall remain the exclusive property of FEMI9 until released through authorized business transactions.</li>
                            <li>Warehouse operations, stock handling, inventory management and stock handover shall be carried out strictly in accordance with the Company's authorized procedures.</li>
                            <li>All operational policies, SOPs, commercial policies and authorized business communications issued by FEMI9 from time to time shall be complied with.</li>
                            <li>All information and documents furnished to FEMI9 in connection with this appointment are true, complete and accurate to the best of the Channel Partner's knowledge.</li>
                            <li>The Channel Partner voluntarily accepts this appointment and undertakes to faithfully perform all obligations contained in this Agreement.</li>
                            <li>The Channel Partner acknowledges that no promise, assurance or representation other than those expressly contained in this Agreement shall be binding upon FEMI9.</li>
                        </ul>

                        <h2>EXECUTION</h2>
                        <p><strong>FOR FEMI9 LLP</strong></p>
                        <div class="agr-field-row"><label>Authorized Signatory Name:</label><span class="agr-value<?php echo empty($settings['authorized_signatory_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($settings['authorized_signatory_name'] ?: 'To be set by Company'); ?></span></div>
                        <div class="agr-field-row"><label>Designation:</label><span class="agr-value<?php echo empty($settings['authorized_signatory_designation']) ? ' agr-blank' : ''; ?>"><?php echo fv($settings['authorized_signatory_designation'] ?: 'To be set by Company'); ?></span></div>

                        <p style="margin-top:18px;"><strong>FOR THE CHANNEL PARTNER</strong></p>
                        <div class="agr-field-row"><label>Name:</label><span class="agr-value"><?php echo fv($cp['name']); ?></span></div>
                        <div class="agr-field-row"><label>Business / Entity Name:</label><span class="agr-value<?php echo empty($cp['company_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($cp['company_name'] ?: 'Not applicable'); ?></span></div>
                        <div class="agr-field-row"><label>Designation:</label>
                            <?php if ($isLocked): ?><span class="agr-value"><?php echo fv($agreement['cp_designation']); ?></span>
                            <?php else: ?><input type="text" name="cp_designation" value="<?php echo fv($agreement['cp_designation']); ?>" placeholder="e.g. Proprietor">
                            <?php endif; ?>
                        </div>
                        <?php if ($isLocked): ?>
                        <div class="agr-field-row"><label>Signature:</label>
                            <?php if (!empty($agreement['cp_signature'])): ?>
                                <img src="<?php echo fv($agreement['cp_signature']); ?>" class="agr-sig-img">
                            <?php else: ?>
                                <span class="agr-value agr-blank">&mdash;</span>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="agr-sig-block">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">Signature:</label>
                            <div id="cpSigContainer" class="no-print"></div>
                            <input type="hidden" name="cp_signature" id="cp_signature_input">
                        </div>
                        <?php endif; ?>

                        <div class="agr-witness-box">
                            <p style="font-weight:700;">WITNESS &ndash; 1</p>
                            <div class="agr-field-row"><label>Name:</label>
                                <?php if ($isLocked): ?><span class="agr-value"><?php echo fv($agreement['witness1_name']); ?></span>
                                <?php else: ?><input type="text" name="witness1_name" value="<?php echo fv($agreement['witness1_name']); ?>">
                                <?php endif; ?>
                            </div>
                            <div class="agr-field-row"><label>Address:</label>
                                <?php if ($isLocked): ?><span class="agr-value"><?php echo fv($agreement['witness1_address']); ?></span>
                                <?php else: ?><input type="text" name="witness1_address" value="<?php echo fv($agreement['witness1_address']); ?>">
                                <?php endif; ?>
                            </div>
                            <?php if ($isLocked): ?>
                            <div class="agr-field-row"><label>Signature:</label>
                                <?php if (!empty($agreement['witness1_signature'])): ?>
                                    <img src="<?php echo fv($agreement['witness1_signature']); ?>" class="agr-sig-img">
                                <?php else: ?>
                                    <span class="agr-value agr-blank">&mdash;</span>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="agr-sig-block">
                                <label style="font-weight:600;display:block;margin-bottom:6px;">Signature:</label>
                                <div id="witness1SigContainer" class="no-print"></div>
                                <input type="hidden" name="witness1_signature" id="witness1_signature_input">
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="agr-witness-box">
                            <p style="font-weight:700;">WITNESS &ndash; 2</p>
                            <div class="agr-field-row"><label>Name:</label>
                                <?php if ($isLocked): ?><span class="agr-value"><?php echo fv($agreement['witness2_name']); ?></span>
                                <?php else: ?><input type="text" name="witness2_name" value="<?php echo fv($agreement['witness2_name']); ?>">
                                <?php endif; ?>
                            </div>
                            <div class="agr-field-row"><label>Address:</label>
                                <?php if ($isLocked): ?><span class="agr-value"><?php echo fv($agreement['witness2_address']); ?></span>
                                <?php else: ?><input type="text" name="witness2_address" value="<?php echo fv($agreement['witness2_address']); ?>">
                                <?php endif; ?>
                            </div>
                            <?php if ($isLocked): ?>
                            <div class="agr-field-row"><label>Signature:</label>
                                <?php if (!empty($agreement['witness2_signature'])): ?>
                                    <img src="<?php echo fv($agreement['witness2_signature']); ?>" class="agr-sig-img">
                                <?php else: ?>
                                    <span class="agr-value agr-blank">&mdash;</span>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="agr-sig-block">
                                <label style="font-weight:600;display:block;margin-bottom:6px;">Signature:</label>
                                <div id="witness2SigContainer" class="no-print"></div>
                                <input type="hidden" name="witness2_signature" id="witness2_signature_input">
                            </div>
                            <?php endif; ?>
                        </div>

                        <h2>ACKNOWLEDGEMENT</h2>
                        <p>By signing this Agreement, the Parties acknowledge that they have read, understood and voluntarily accepted all the terms and conditions contained herein and agree to be legally bound by the provisions of this Division Channel Partner Agreement. This Agreement shall become effective from the Effective Date specified in Schedule&nbsp;&ndash;&nbsp;1 and shall remain in force until terminated in accordance with the provisions of this Agreement.</p>

                        <?php if (!$isLocked): ?>
                        <div class="no-print" style="margin-top:24px;text-align:center;">
                            <button type="submit" class="btn btn-success" style="padding:10px 28px;font-weight:600;">Accept &amp; Sign Agreement</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<?php if (!$isLocked): ?>
<script src="../../assets/js/signature-pad.js"></script>
<script>
    initSignaturePad('cpSigContainer', 'cp_signature_input');
    initSignaturePad('witness1SigContainer', 'witness1_signature_input');
    initSignaturePad('witness2SigContainer', 'witness2_signature_input');

    function prepareSignaturesAndValidate() {
        // Only the Channel Partner's own signature is mandatory — witnesses
        // are optional, and filling in just one of the two is fine (not
        // both required). Confirmed 2026-09-28.
        var cpSig = document.getElementById('cp_signature_input').value;
        if (!cpSig) { alert('Please provide your signature (draw or type) before submitting.'); return false; }
        return confirm('Once submitted, this agreement cannot be edited. Do you want to submit it now?');
    }
</script>
<?php endif; ?>
</body>
</html>
