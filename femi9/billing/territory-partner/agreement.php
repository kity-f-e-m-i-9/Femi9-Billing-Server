<?php
include("checksession.php");
require_once __DIR__ . '/../shared/AgreementService.php';
include("config.php");
error_reporting(0);

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$tp_id = (int) $Login_user_IDvl;

$stmtTp = $db_conn->prepare("SELECT * FROM territory_partners WHERE id = ?");
$stmtTp->bind_param('i', $tp_id);
$stmtTp->execute();
$tp = $stmtTp->get_result()->fetch_assoc();
$stmtTp->close();

$settings  = get_agreement_settings($db_conn);
$agreement = get_or_create_tp_agreement($db_conn, $tp_id);
$isLocked  = (int) ($agreement['is_locked'] ?? 0) === 1;

if (isset($_SESSION['sucMessage'])) { $flashMsg = $_SESSION['sucMessage']; unset($_SESSION['sucMessage']); }
if (isset($_SESSION['errorMessage'])) { $flashErr = $_SESSION['errorMessage']; unset($_SESSION['errorMessage']); }

function fv($val) { return htmlspecialchars((string) ($val ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Territory Partner Agreement : <?php echo fv($business_name ?? 'Femi9'); ?></title>
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
                        <h1 style="font-size:20px;font-weight:600;color:#1f2937;margin:0;">Territory Partner Agreement</h1>
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
                        <h1>FEMI9 LLP - TERRITORY PARTNER AGREEMENT</h1>

                        <p>This Territory Partner Agreement (hereinafter referred to as the "Agreement") is made and executed on the date and at the place specified below and shall become effective from the Effective Date recorded in Schedule&nbsp;&ndash;&nbsp;1.</p>

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
                        <div class="agr-field-row"><label>Approved Initial Monthly Purchase Commitment:</label>
                            <span class="agr-value<?php echo $agreement['monthly_purchase_commitment'] === null ? ' agr-blank' : ''; ?>">
                                <?php echo $agreement['monthly_purchase_commitment'] !== null ? 'Rs. ' . fv(number_format((float)$agreement['monthly_purchase_commitment'], 2)) . ' per Month' : 'To be set by Company'; ?>
                            </span>
                        </div>
                        <p class="text-muted" style="font-size:12px;margin-top:-6px;">(Subject to periodic review by FEMI9 in accordance with Clause 10 and Schedule &ndash; 1.)</p>

                        <h2>BETWEEN</h2>
                        <p><strong>FEMI9 LLP</strong>, a Limited Liability Partnership duly incorporated under the provisions of the Limited Liability Partnership Act, 2008, having its Registered Office / Principal Place of Business at:</p>
                        <p class="agr-value<?php echo empty($settings['company_address']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($settings['company_address'] ?: 'To be filled by Company')); ?></p>
                        <p>(hereinafter referred to as "FEMI9" or the "Company", which expression shall, unless repugnant to the context or meaning thereof, include its successors, legal representatives, permitted assigns and administrators);</p>

                        <h2>AND</h2>
                        <p><strong>TERRITORY PARTNER</strong></p>
                        <div class="agr-field-row"><label>Name:</label><span class="agr-value"><?php echo fv($tp['name']); ?></span></div>
                        <div class="agr-field-row"><label>Business / Entity Name:</label><span class="agr-value<?php echo empty($tp['company_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($tp['company_name'] ?: 'Not applicable'); ?></span></div>
                        <div class="agr-field-row"><label>Address:</label><span class="agr-value"><?php echo fv($tp['address']); ?></span></div>
                        <div class="agr-field-row"><label>Mobile Number:</label><span class="agr-value"><?php echo fv($tp['mobile']); ?></span></div>
                        <div class="agr-field-row"><label>Email Address:</label><span class="agr-value<?php echo empty($tp['email']) ? ' agr-blank' : ''; ?>"><?php echo fv($tp['email'] ?: '—'); ?></span></div>
                        <p>(hereinafter referred to as the "Territory Partner" or "TP", which expression shall, unless repugnant to the context or meaning thereof, include, where applicable, its successors, legal representatives, heirs, executors, administrators and permitted assigns).</p>
                        <p>FEMI9 and the Territory Partner are hereinafter individually referred to as a "Party" and collectively as the "Parties."</p>

                        <h2>1. PURPOSE OF APPOINTMENT</h2>
                        <p>FEMI9 hereby appoints the Territory Partner to develop, distribute, service and expand the market for the Company's authorised products within the territory / Firka officially allotted by the Company, subject to the terms and conditions of this Agreement. The Territory Partner shall operate as an independent business partner and shall be responsible for the systematic development of the allotted territory through active retail expansion, product distribution, outlet servicing, market penetration and sustainable business growth. The appointment shall take effect from the Effective Date specified in Schedule&nbsp;&ndash;&nbsp;1 and shall remain subject to the Territory Partner's continued compliance with this Agreement and the Company's applicable operational policies.</p>

                        <h2>2. TERRITORY / FIRKA ALLOCATION</h2>
                        <p>The Territory Partner is hereby authorised to conduct business only within the territory / Firka officially allotted by FEMI9 and recorded in Schedule&nbsp;&ndash;&nbsp;1. The allotted territory: confers upon the Territory Partner an authorised business opportunity within the assigned geographical area; does not constitute ownership, lease, purchase or any proprietary right over the allotted territory; shall not be transferred, assigned, sub-licensed or otherwise dealt with without the prior written approval of FEMI9; and shall remain subject to active business development, satisfactory market coverage, operational performance and continuing compliance with the provisions of this Agreement. FEMI9 reserves the right to review, modify, reallocate or restructure the allotted territory where considered necessary for business, operational or strategic reasons, in accordance with this Agreement.</p>

                        <h2>3. CORE ROLE AND RESPONSIBILITIES</h2>
                        <p>The Territory Partner shall be primarily responsible for the continuous development, distribution and servicing of the allotted market and shall perform such responsibilities diligently, professionally and in the best interests of the Company. Without limitation, the Territory Partner shall: develop and expand retail business throughout the allotted territory; identify, enroll and activate new retail outlets; maintain regular servicing and business relationships with existing outlets; execute the route plan assigned or approved by the Company's authorised sales management; ensure timely order fulfilment and uninterrupted product supply; maintain adequate field operations, manpower and delivery capability; support Company-approved product visibility initiatives and in-shop branding activities; achieve applicable sales, distribution and market development objectives; maintain accurate business records and submit reports as required by the Company; and maintain continuous coordination with the Company's authorised sales, operational and support teams. The Territory Partner shall perform all responsibilities in a manner that protects the reputation, goodwill and commercial interests of FEMI9.</p>

                        <h2>4. DAILY ROUTE PLAN AND FIELD EXECUTION</h2>
                        <p>Active field execution constitutes a fundamental obligation of the Territory Partner under this Agreement. The Territory Partner shall strictly adhere to the daily, weekly or periodic route plans assigned or approved by the Company's authorised sales management and shall ensure systematic market coverage throughout the allotted territory. Where the Territory Partner is unable to personally execute the approved route plan, the Territory Partner shall deploy suitably trained and authorised field personnel to ensure uninterrupted execution of market activities and retail servicing. Failure to maintain active field execution, consistent market visits or regular outlet servicing may be treated as operational non-performance under this Agreement. Mere allotment or holding of a territory shall not, by itself, constitute satisfactory performance or confer any permanent or unconditional right to continue such territory.</p>

                        <h2>5. DAILY REPORTING AND COMPANY COORDINATION</h2>
                        <p>The Territory Partner shall maintain regular communication and effective coordination with the Company's authorised sales management, including the applicable District Sales Manager and such other authorised Company representatives as may be designated from time to time. The Territory Partner shall submit End-of-Day (EOD) reports and such other operational reports through the reporting systems or processes prescribed by FEMI9. Reporting requirements may include, wherever applicable: route executed; retail outlets visited; new outlet identification and activation; orders received; orders supplied or serviced; sales and distribution activities; market intelligence and customer feedback; outstanding issues requiring Company support; and any other operational information reasonably required by FEMI9 for effective territory management. Regular route execution, timely reporting and continuous coordination with the Company's authorised management shall constitute essential obligations for the continued operation and retention of the allotted territory under this Agreement.</p>

                        <h2>6. MARKET DEVELOPMENT &amp; RETAIL EXPANSION</h2>
                        <p>The Territory Partner shall actively develop, expand and strengthen the market within the allotted territory by ensuring maximum product availability, increasing retail penetration and promoting sustainable business growth, across all authorised trade channels including but not limited to Retail Shops; Pharmacies and Medical Stores; Supermarkets and Modern Trade Outlets; General Trade Establishments; Educational Institutions; Hospitals, Clinics and Healthcare Institutions, where applicable; and any other authorised business channels approved by FEMI9 from time to time. The Territory Partner shall use commercially reasonable efforts to increase the number of active retail outlets; improve product availability; generate repeat business; expand product penetration; enhance product visibility through Company-approved branding initiatives; strengthen retailer relationships and customer satisfaction; and consistently improve the overall business performance of the allotted territory, in accordance with the Company's approved business strategy, operational guidelines and brand standards.</p>

                        <h2>7. COMPANY-GENERATED MARKET OPPORTUNITIES &amp; ORDERS</h2>
                        <p>FEMI9 may, at its discretion, deploy its authorised sales force or business development team to identify new market opportunities, develop prospective customers and facilitate business expansion within any allotted territory. Where an outlet, institution or customer is developed, or an order is generated, by the Company's authorised representatives within the Territory Partner's allotted area, the Territory Partner shall promptly fulfil such order and provide timely product supply and customer service in accordance with the Company's approved operational procedures, and shall not unreasonably refuse, delay or neglect any authorised market opportunity or order allocated by the Company. Any market development support extended by FEMI9 shall be considered an additional business facilitation measure and shall not diminish or replace the Territory Partner's independent responsibility to actively develop, service and expand the allotted territory.</p>

                        <h2>8. PRODUCT PORTFOLIO &amp; MARKET EXPANSION</h2>
                        <p>The Territory Partner shall actively promote, distribute and support the authorised portfolio of FEMI9 products applicable to the allotted territory, which may from time to time be expanded to include newly introduced products, product variants or additional categories launched by FEMI9. Upon introduction of any authorised product, the Territory Partner shall make commercially reasonable efforts to develop market acceptance; ensure adequate retail availability; expand product distribution; increase market penetration; and support approved promotional and product launch initiatives. The introduction of new products or expansion of the Company's product portfolio shall automatically form part of this Agreement and shall not require execution of a separate amendment unless otherwise expressly notified by FEMI9.</p>

                        <h2>9. MANPOWER &amp; DELIVERY CAPABILITY</h2>
                        <p>The Territory Partner shall maintain adequate operational infrastructure, manpower and logistical capability necessary for the efficient management and servicing of the allotted territory, including, depending upon operational requirements, either directly or through authorised personnel: sufficient field representatives or line sales personnel; appropriate order servicing capability; adequate delivery personnel; suitable transportation or delivery vehicles; and such additional operational resources as may reasonably be required. The Territory Partner shall ensure that deficiencies in manpower, logistics or transportation do not adversely affect market coverage, order fulfilment, retailer servicing or customer satisfaction. Repeated operational deficiencies arising from inadequate manpower or delivery capability may be considered a material performance issue under this Agreement.</p>

                        <h2>10. PURCHASE, PAYMENT &amp; STOCK PROCUREMENT</h2>
                        <p>The Territory Partner shall procure products exclusively through the authorized business channels and payment mechanisms prescribed by FEMI9. The Approved Initial Monthly Purchase Commitment applicable to the Territory Partner shall be determined by FEMI9 based on the assessed business potential of the allotted territory and shall be recorded in this Agreement and Schedule&nbsp;&ndash;&nbsp;1, constituting the minimum monthly purchase obligation for the commencement of business operations. The Territory Partner acknowledges that this commitment may be periodically reviewed by FEMI9 based on legitimate commercial and operational considerations, including sustained growth in business volume; expansion of the allotted territory or market coverage; increase in active retail outlets or distribution capacity; product portfolio expansion; business performance and operational capability; or any other reasonable commercial or operational requirement determined by the Company. Any such revision shall be communicated through the Company's authorized communication channels and shall become effective from the date specified in such communication, automatically superseding the previous commitment and deemed incorporated into Schedule&nbsp;&ndash;&nbsp;1 without requiring the execution of a fresh Agreement. All purchases shall be subject to the Company's prevailing commercial policies, including Product Pricing; Billing Procedures; Trade Margins; Promotional Offers; Sales Schemes; Applicable Taxes; and other approved commercial benefits, which FEMI9 reserves the right to revise from time to time. Products shall be supplied only against duly authorized and verified purchase transactions processed through the Company's approved systems. The Territory Partner shall not claim any pricing, trade margin, promotional scheme, incentive or commercial benefit unless expressly approved by FEMI9 in writing or communicated through an authorized Company communication, and no verbal assurance, informal commitment or unauthorized representation made by any person shall be binding upon FEMI9 unless confirmed through the Company's authorized communication process.</p>

                        <h2>11. STOCK COLLECTION &amp; DELIVERY RESPONSIBILITY</h2>
                        <p>The Territory Partner shall collect, receive and acknowledge authorised stock exclusively through the Company's approved distribution, dispatch and stock handover procedures. Upon the authorised handover of products by FEMI9 or its authorised logistics partner, the responsibility for the subsequent custody, transportation and distribution of such products shall immediately vest with the Territory Partner. Unless otherwise expressly agreed in writing by FEMI9, the Territory Partner shall be solely responsible for transportation of products; safe handling and storage of stock; protection against loss, damage or deterioration; delivery to retailers, distributors or other authorised customers; local logistics and distribution management; and all transportation, delivery and handling expenses incurred after authorised stock handover. The Territory Partner shall ensure that all deliveries are completed accurately, efficiently and within the timelines reasonably expected by the Company and its customers. Failure to maintain timely and reliable delivery standards resulting in repeated disruption of business operations may constitute a material performance deficiency under this Agreement.</p>

                        <h2>12. SALES TARGETS &amp; BUSINESS PERFORMANCE</h2>
                        <p>FEMI9 may, from time to time, establish reasonable business objectives and performance benchmarks for the Territory Partner in order to ensure systematic market development and sustainable business growth, including Sales Targets; Outlet Coverage Targets; New Outlet Activation Targets; Product Penetration Targets; Route Productivity Standards; Collection or Recovery Targets, where applicable; and other measurable operational or commercial objectives. The Territory Partner acknowledges that such targets are intended to promote continuous business development, improve market penetration and evaluate overall territory performance. Performance may be assessed based upon sales achievement; market coverage; growth in active retail outlets; route execution efficiency; timely order fulfilment; reporting discipline; product availability; retail servicing standards; coordination with the Company's authorised representatives; and overall development and productivity of the allotted territory. The Company may periodically review performance and provide guidance, recommendations or corrective measures for improvement.</p>

                        <h2>13. REFERRAL BENEFIT OPPORTUNITY</h2>
                        <p>To encourage the expansion of the Company's distribution network, FEMI9 may provide eligible Territory Partners with an opportunity to refer suitable individuals or business entities for appointment as Territory Partners. Where such referral results in the successful appointment of a Territory Partner duly approved by FEMI9, the referring Territory Partner may become eligible to receive the applicable Referral Benefit or Referral Commission in accordance with the Company's prevailing Referral Benefit Policy, subject to the terms, conditions and eligibility criteria prescribed by FEMI9 from time to time, including that both the referring and referred Territory Partners continue to maintain an Active and Eligible Territory Partner status (unless otherwise provided under the applicable policy). The amount, method of calculation, payment schedule, duration, continuation, suspension or discontinuation of Referral Benefits shall be governed exclusively by the Company's authorized Referral Benefit Policy as amended from time to time. The Referral Benefit constitutes an additional business incentive and shall not form part of the Territory Partner's regular commercial margin, trade discount or contractual entitlement.</p>

                        <h2>14. BRAND PROTECTION, PRICING &amp; MARKET DISCIPLINE</h2>
                        <p>The Territory Partner shall at all times conduct its business in a manner that protects and enhances the reputation, goodwill, commercial interests and market credibility of FEMI9, and shall strictly comply with all authorized pricing policies, branding guidelines, marketing standards and operational procedures issued by the Company from time to time. Without limitation, the Territory Partner shall not directly or indirectly engage in unauthorized pricing, discounting or billing practices; false, misleading or unsubstantiated product claims; unauthorized use, alteration or reproduction of FEMI9 trademarks, logos, packaging or other brand assets; diversion of stock outside the authorized distribution channel; manipulation or falsification of Company records, systems or reports; misrepresentation of authority or business status; fraudulent or deceptive business practices; counterfeit, duplicate or unauthorized product dealings; or any act or omission likely to damage the reputation, goodwill, consumers or authorized distribution network of FEMI9. The Territory Partner shall not sell, distribute, promote, or otherwise deal in any competing Sanitary Pad brands or any competing products in future product categories introduced by FEMI9, including Baby Diapers, Adult Diapers, Wet Wipes, and any other products launched by the Company. All trademarks, trade names, logos, product names, copyrights, packaging designs, promotional materials and other intellectual property associated with FEMI9 shall remain the exclusive property of the Company or its lawful owner, and nothing contained in this Agreement shall be construed as granting the Territory Partner any ownership or proprietary rights therein except the limited right to use such materials strictly in accordance with the Company's written authorisation.</p>

                        <h2>15. TERRITORY PERFORMANCE &amp; CONTINUATION OF APPOINTMENT</h2>
                        <p>The continuation of the Territory Partner's appointment and the retention of the allotted territory shall be subject to the Territory Partner's consistent operational performance, business development efforts and compliance with the terms of this Agreement, including execution of approved route plans; active field operations and market coverage; submission of EOD and other required reports; timely servicing of retailers and customers; efficient product supply and order fulfilment; market development and business expansion; achievement of reasonable sales and operational objectives; adequate manpower, logistics and delivery capability; product availability across the allotted territory; compliance with Company policies and operational procedures; and continuous cooperation with the Company's authorised sales and management personnel. The allotted territory shall not be regarded as a permanent, vested or unconditional right. FEMI9 reserves the right to periodically review the Territory Partner's performance and, where necessary, modify, restructure, reduce, reassign or discontinue the allotted territory in accordance with this Agreement and the Company's legitimate business requirements.</p>

                        <h2>16. NON-PERFORMANCE &amp; CORRECTIVE ACTION</h2>
                        <p>Where the Territory Partner fails to perform any material obligation under this Agreement or repeatedly fails to meet the Company's reasonable operational or commercial expectations, FEMI9 may notify the Territory Partner of such deficiency and require appropriate corrective action within a reasonable period, intended to restore satisfactory operational performance and ensure continued development of the allotted territory. Circumstances that may warrant corrective action include, but are not limited to: repeated failure to execute approved route plans; failure to submit EOD or other required business reports; persistent delays in servicing orders or supplying products; inadequate market coverage or poor retail servicing; failure to develop new retail outlets or expand market penetration; repeated non-cooperation with the Company's authorised personnel; inadequate manpower, logistics or delivery capability affecting business operations; consistent underperformance against reasonable business objectives without genuine corrective effort; or any other material operational non-compliance. Where the Territory Partner fails to rectify such deficiencies within the reasonable period specified, FEMI9 may issue further written warnings or operational directions; restrict or suspend specified business operations; review the Territory Partner's appointment; reduce, modify or restructure the allotted territory; reallocate all or part of the territory to another authorised business partner; or terminate this Agreement, without prejudice to any other rights or remedies available under this Agreement or applicable law.</p>

                        <h2>17. SERIOUS BREACH &amp; IMMEDIATE ACTION</h2>
                        <p>Notwithstanding any other provision of this Agreement, FEMI9 reserves the right to take immediate protective, suspension or termination action where the Territory Partner commits any act that, in the reasonable opinion of the Company, constitutes a serious breach of this Agreement or poses an immediate risk to the Company's business, assets, reputation or consumers, including without limitation: fraud or dishonest conduct; theft, misappropriation or unauthorised use of Company funds, stock or assets; deliberate financial manipulation or accounting irregularities; significant diversion of stock outside the authorised distribution channel; forgery, falsification or manipulation of Company records, reports or systems; serious misuse of the Company's trademarks, brand identity or intellectual property; unauthorised access to or manipulation of Company software, digital platforms or reporting systems; material violation of any applicable law, regulation or statutory requirement; bribery, corruption or unethical business practices; wilful misconduct causing substantial financial or reputational loss to FEMI9; or any other act likely to cause immediate and significant harm to the Company, its consumers or its authorised distribution network. In such circumstances, FEMI9 shall be entitled to take immediate action, including suspension or termination of the Territory Partner's appointment, without being required to provide an ordinary corrective period where immediate intervention is reasonably necessary. Nothing contained herein shall restrict FEMI9 from pursuing any civil, criminal or statutory remedy available under applicable law.</p>

                        <h2>18. INDEPENDENT BUSINESS RELATIONSHIP</h2>
                        <p>The Territory Partner is appointed solely as an independent business partner and shall conduct its business independently and at its own cost and risk, subject to the provisions of this Agreement. Nothing contained in this Agreement shall be construed as creating or implying an employer-employee relationship; a partnership, joint venture or association between the Parties; any equity participation or ownership interest in FEMI9; an agency relationship with unrestricted authority; or any proprietary right over the Company's business, products or allotted territory. The Territory Partner shall not represent itself as having authority to bind, commit or create any legal obligation on behalf of FEMI9 except to the extent expressly authorised in writing by the Company, and shall remain solely responsible for its own employees, representatives, statutory compliances, taxes, licences, insurance, business expenses and operational liabilities arising from its business activities.</p>

                        <h2>19. CONFIDENTIALITY &amp; BUSINESS INFORMATION</h2>
                        <p>The Territory Partner acknowledges that, during the course of its business relationship with FEMI9, it may obtain access to confidential, proprietary or commercially sensitive information belonging to the Company, and shall maintain the strict confidentiality of all such information, using the same solely for the purpose of performing its obligations under this Agreement. Confidential Information may include, without limitation: pricing structures and commercial policies; customer, retailer and distributor information; sales reports and business data; marketing plans and business strategies; product information and future business initiatives; Company software, systems and operational processes; internal reports, financial information and management communications; and any other information designated by FEMI9 as confidential or which, by its nature, ought reasonably to be treated as confidential. The Territory Partner shall not disclose, copy, reproduce, publish or otherwise make available any Confidential Information to any third party without the prior written consent of FEMI9, except where disclosure is required by applicable law or a competent legal authority. The obligations contained in this Clause shall survive the expiration or termination of this Agreement.</p>

                        <h2>20. TERM, VOLUNTARY EXIT &amp; TERMINATION</h2>
                        <p>This Agreement shall commence on the Effective Date specified in Schedule&nbsp;&ndash;&nbsp;1 and shall continue unless terminated in accordance with the provisions of this Agreement. The Territory Partner may voluntarily discontinue its appointment by providing not less than Thirty (30) Days' prior written notice to FEMI9, unless otherwise mutually agreed in writing. During the notice period, the Territory Partner shall cooperate fully with the Company and complete all pending obligations, including reconciliation of outstanding accounts and payments; completion of pending customer orders; return of Company property, documents, promotional materials or other assets; stock verification and reconciliation, where applicable; submission of pending reports and business records; and any other reasonable transition or handover requirement communicated by FEMI9. FEMI9 may terminate this Agreement by written notice where the Territory Partner commits a material breach of this Agreement; persistent operational deficiencies remain unresolved despite reasonable corrective opportunities; the Territory Partner engages in fraud, misconduct or any serious breach referred to under Clause 17; the Territory Partner ceases to carry on business or becomes insolvent, bankrupt or subject to legal proceedings materially affecting its ability to perform this Agreement; or continuation of the appointment is considered commercially or operationally detrimental to the legitimate interests of FEMI9. Termination shall be without prejudice to any rights, obligations, liabilities or remedies accrued by either Party prior to the effective date of termination.</p>

                        <h2>21. EFFECT OF EXIT OR TERMINATION</h2>
                        <p>Upon the effective date of the Territory Partner's voluntary exit or termination of this Agreement for any reason whatsoever, all rights and authorizations granted to the Territory Partner shall automatically cease unless otherwise expressly approved by FEMI9 in writing. Without prejudice to any accrued rights or obligations, the Territory Partner shall immediately cease representing itself as an authorised Territory Partner of FEMI9; discontinue the use of the Company's name, trademarks, logos, branding, promotional materials and any other intellectual property, except to the extent expressly authorised; return all Company property, documents, records, promotional materials, identification items, software access credentials and any other assets belonging to FEMI9, where applicable; complete reconciliation of all outstanding commercial transactions, payments, stock records and other financial obligations; cooperate in the orderly transition or transfer of business responsibilities, where reasonably required by FEMI9; and comply with all continuing obligations under this Agreement which, by their nature, are intended to survive termination. Following reconciliation and subject to applicable laws, Company policies and verification of accounts, FEMI9 shall process any legitimate commercial settlements or payments due to the Territory Partner. Termination of this Agreement shall not affect any rights, remedies or liabilities accrued prior to the effective date of termination.</p>

                        <h2>22. COMPANY POLICIES &amp; OPERATIONAL UPDATES</h2>
                        <p>The Territory Partner shall comply with all operational policies, Standard Operating Procedures (SOPs), business guidelines, commercial policies and authorised operational instructions issued by FEMI9 from time to time. FEMI9 reserves the right to introduce, amend or update such operational policies and procedures as may be reasonably required for business growth and expansion; technology implementation and system improvements; distribution efficiency; product quality and consumer protection; regulatory or statutory compliance; operational effectiveness; and changing market or business requirements. The Territory Partner agrees to implement such operational updates within a reasonable period communicated by the Company. No operational policy, circular or administrative instruction shall retrospectively alter or deprive the Territory Partner of any valid commercial entitlement that has already accrued under this Agreement unless required by law or expressly agreed by the Parties.</p>

                        <h2>23. AMENDMENT &amp; FORMAL COMMUNICATION</h2>
                        <p>No amendment, modification or variation of the material terms of this Agreement shall be valid unless made in writing and duly authorised by FEMI9. Routine operational communications, business instructions, notices and commercial updates may be communicated through any of the following authorised channels: Official Company Letters; Company-authorised Digital Portals or Software Systems; Official Email Communications; Company-approved Messaging Platforms; Mobile Applications; or any other official communication channel designated by FEMI9. The Territory Partner acknowledges that communications issued through the Company's authorised channels shall constitute valid business communications, and that no verbal assurance, informal representation or unauthorised commitment made by any employee, representative or third party shall amend, modify or override the express provisions of this Agreement unless confirmed in writing by an authorised representative of FEMI9.</p>

                        <h2>24. GOVERNING LAW &amp; JURISDICTION</h2>
                        <p>This Agreement shall be governed by, interpreted and construed in accordance with the laws of the Republic of India. The Parties shall endeavor to resolve, in good faith, any dispute, controversy or claim arising out of or relating to this Agreement through mutual discussions and amicable settlement. In the event that such dispute cannot be resolved amicably within a reasonable period, the Parties agree that the matter shall be subject to the exclusive jurisdiction of the competent courts situated at <strong>ERODE DISTRICT, TAMIL NADU</strong>. Nothing contained herein shall restrict FEMI9 from seeking any interim, injunctive or other appropriate relief before any court or authority having competent jurisdiction, where such relief is necessary to protect its legal rights or business interests.</p>

                        <h2>25. ENTIRE AGREEMENT</h2>
                        <p>This Agreement, together with Schedule&nbsp;&ndash;&nbsp;1 (Territory / Firka Allocation &amp; Commercial Particulars) and any written amendment duly executed by the Parties, constitutes the complete and entire agreement governing the appointment of the Territory Partner, and supersedes all prior discussions, negotiations, understandings, representations or communications relating to the subject matter hereof, whether oral or written. The Parties acknowledge that they have not relied upon any statement, promise or representation other than those expressly contained in this Agreement. Operational policies, SOPs, commercial guidelines and administrative instructions issued by FEMI9 shall support the implementation of this Agreement but shall not override or modify any express contractual provision contained herein unless incorporated through an authorised written amendment. In the event that any provision of this Agreement is held to be invalid, illegal or unenforceable by a court of competent jurisdiction, the remaining provisions shall continue in full force and effect to the maximum extent permitted by law. No failure or delay by either Party in exercising any right or remedy under this Agreement shall operate as a waiver of such right or remedy.</p>

                        <h2>SCHEDULE &ndash; 1: TERRITORY / FIRKA ALLOCATION &amp; COMMERCIAL PARTICULARS</h2>
                        <p>The following particulars shall form an integral part of this Territory Partner Agreement and shall be read together with all the terms and conditions contained herein.</p>
                        <div class="agr-field-row"><label>Territory Partner Name:</label><span class="agr-value"><?php echo fv($tp['name']); ?></span></div>
                        <div class="agr-field-row"><label>Territory Partner ID / Code:</label><span class="agr-value"><?php echo fv($tp['tp_id']); ?></span></div>
                        <div class="agr-field-row"><label>State:</label><span class="agr-value<?php echo empty($tp['branch_state']) ? ' agr-blank' : ''; ?>"><?php echo fv($tp['branch_state'] ?: '—'); ?></span></div>
                        <div class="agr-field-row"><label>District:</label><span class="agr-value<?php echo empty($tp['assigned_district']) ? ' agr-blank' : ''; ?>"><?php echo fv($tp['assigned_district'] ?: '—'); ?></span></div>
                        <div class="agr-field-row"><label>Taluk / Block:</label><span class="agr-value<?php echo empty($agreement['taluk_block']) ? ' agr-blank' : ''; ?>"><?php echo fv($agreement['taluk_block'] ?: 'To be set by Company'); ?></span></div>
                        <div class="agr-field-row"><label>Allotted Firka / Territory:</label><span class="agr-value<?php echo empty($agreement['territory_firka']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['territory_firka'] ?: 'To be set by Company')); ?></span></div>
                        <div class="agr-field-row"><label>Territory Code:</label><span class="agr-value<?php echo empty($agreement['territory_code']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['territory_code'] ?: '—')); ?></span></div>
                        <div class="agr-field-row"><label>Effective Date:</label><span class="agr-value<?php echo empty($agreement['effective_date']) ? ' agr-blank' : ''; ?>"><?php echo $agreement['effective_date'] ? fv(date('d-m-Y', strtotime($agreement['effective_date']))) : 'To be set by Company'; ?></span></div>
                        <div class="agr-field-row"><label>Approved Monthly Purchase Commitment:</label><span class="agr-value<?php echo $agreement['monthly_purchase_commitment'] === null ? ' agr-blank' : ''; ?>"><?php echo $agreement['monthly_purchase_commitment'] !== null ? 'Rs. ' . fv(number_format((float)$agreement['monthly_purchase_commitment'], 2)) . ' per Month' : 'To be set by Company'; ?></span></div>
                        <div class="agr-field-row"><label>Other Approved Commercial Particulars:</label><span class="agr-value<?php echo empty($agreement['other_particulars']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['other_particulars'] ?: '—')); ?></span></div>
                        <p>The above Territory / Firka allocation is granted exclusively for the purpose of carrying on authorised business activities under this Agreement and shall remain subject to the Territory Partner's continued performance, compliance and fulfilment of all obligations set out in this Agreement. Any modification to the above particulars shall be valid only if approved and communicated by FEMI9 through an authorised written process.</p>

                        <h2>FINAL DECLARATION &amp; ACCEPTANCE</h2>
                        <p>The Territory Partner hereby declares, confirms and agrees that:</p>
                        <ul class="agr-ul">
                            <li>This Agreement has been carefully read, fully understood and voluntarily accepted.</li>
                            <li>The rights, responsibilities, obligations and conditions governing the allotted territory have been clearly explained and are fully understood.</li>
                            <li>Active market development, field execution, retail servicing and business expansion are fundamental responsibilities of the Territory Partner.</li>
                            <li>The requirement to execute approved route plans, maintain regular market coverage, submit EOD reports and coordinate with the Company's authorised representatives is understood and accepted.</li>
                            <li>Continuation of the allotted territory is subject to satisfactory business performance, operational compliance and adherence to the terms of this Agreement.</li>
                            <li>The Territory Partner acknowledges that the Approved Monthly Purchase Commitment may be revised by FEMI9 from time to time based on the assessed business potential of the allotted territory and agrees to comply with such revised commitment from its effective date.</li>
                            <li>Responsibility for transportation, storage, handling and local distribution of products after authorised stock handover shall rest with the Territory Partner unless otherwise approved in writing by FEMI9.</li>
                            <li>The Referral Benefit opportunity, wherever applicable, shall be governed exclusively by the Company's prevailing Referral Benefit Policy.</li>
                            <li>The Territory Partner shall comply with all applicable Company policies, SOPs, operational guidelines and authorised business communications issued from time to time.</li>
                            <li>All information furnished to FEMI9 in connection with this appointment is true, complete and accurate to the best of the Territory Partner's knowledge and belief.</li>
                        </ul>
                        <p>The Territory Partner voluntarily accepts this appointment and agrees to faithfully perform all obligations contained in this Agreement.</p>

                        <h2>EXECUTION</h2>
                        <p><strong>FOR FEMI9 LLP</strong></p>
                        <div class="agr-field-row"><label>Authorized Signatory Name:</label><span class="agr-value<?php echo empty($settings['authorized_signatory_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($settings['authorized_signatory_name'] ?: 'To be set by Company'); ?></span></div>
                        <div class="agr-field-row"><label>Designation:</label><span class="agr-value<?php echo empty($settings['authorized_signatory_designation']) ? ' agr-blank' : ''; ?>"><?php echo fv($settings['authorized_signatory_designation'] ?: 'To be set by Company'); ?></span></div>

                        <p style="margin-top:18px;"><strong>FOR THE TERRITORY PARTNER</strong></p>
                        <div class="agr-field-row"><label>Name:</label><span class="agr-value"><?php echo fv($tp['name']); ?></span></div>
                        <div class="agr-field-row"><label>Business / Entity Name:</label><span class="agr-value<?php echo empty($tp['company_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($tp['company_name'] ?: 'Not applicable'); ?></span></div>
                        <div class="agr-field-row"><label>Designation:</label>
                            <?php if ($isLocked): ?><span class="agr-value"><?php echo fv($agreement['tp_designation']); ?></span>
                            <?php else: ?><input type="text" name="tp_designation" value="<?php echo fv($agreement['tp_designation']); ?>" placeholder="e.g. Proprietor">
                            <?php endif; ?>
                        </div>
                        <?php if ($isLocked): ?>
                        <div class="agr-field-row"><label>Signature:</label>
                            <?php if (!empty($agreement['tp_signature'])): ?>
                                <img src="<?php echo fv($agreement['tp_signature']); ?>" class="agr-sig-img">
                            <?php else: ?>
                                <span class="agr-value agr-blank">&mdash;</span>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="agr-sig-block">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">Signature:</label>
                            <div id="tpSigContainer" class="no-print"></div>
                            <input type="hidden" name="tp_signature" id="tp_signature_input">
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
                        <p>By signing this Agreement, the Parties acknowledge that they have read, understood and voluntarily accepted all the terms and conditions contained herein and agree to be legally bound by the provisions of this Territory Partner Agreement. This Agreement shall become effective from the Effective Date specified in Schedule&nbsp;&ndash;&nbsp;1 and shall remain binding upon the Parties until terminated in accordance with its terms.</p>

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
    initSignaturePad('tpSigContainer', 'tp_signature_input');
    initSignaturePad('witness1SigContainer', 'witness1_signature_input');
    initSignaturePad('witness2SigContainer', 'witness2_signature_input');

    function prepareSignaturesAndValidate() {
        // Only the Territory Partner's own signature is mandatory —
        // witnesses are optional, and filling in just one of the two is
        // fine (not both required). Confirmed 2026-09-28.
        var tpSig = document.getElementById('tp_signature_input').value;
        if (!tpSig) { alert('Please provide your signature (draw or type) before submitting.'); return false; }
        return confirm('Once submitted, this agreement cannot be edited. Do you want to submit it now?');
    }
</script>
<?php endif; ?>
</body>
</html>
