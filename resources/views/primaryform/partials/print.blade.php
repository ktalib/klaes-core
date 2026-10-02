<div id="printTemplate" style="display:none;">
    <div style="font-family: Arial, sans-serif; max-width: 210mm; margin: 0 auto; padding: 10mm; font-size: 11px; line-height: 1.3;">
      <div style="text-align: center; margin-bottom: 15px;">
        <h1 style="margin-bottom: 3px; font-size: 16px; font-weight: bold;">MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
        <h2 style="margin-top: 0; font-size: 14px; font-weight: bold;">SECTIONAL TITLING APPLICATION SLIP</h2>
      </div>
      
      <div style="margin-bottom: 12px; border-bottom: 1px solid #ccc; padding-bottom: 8px;">
        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
          <tr>
            <td style="width: 33%; padding: 2px 0;"><strong>Application ID:</strong> <span id="print-app-id"></span></td>
            <td style="width: 33%; padding: 2px 0; text-align: center;"><strong>Date:</strong> <span id="print-date"></span></td>
            <td style="width: 34%; padding: 2px 0; text-align: right;"><strong>Land Use:</strong> Residential</td>
          </tr>
        </table>
      </div>
      
      <!-- Two-column layout using table for better print compatibility -->
      <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
          <td style="width: 48%; vertical-align: top; padding-right: 2%;">
            <h3 style="font-size: 13px; margin-bottom: 6px; border-bottom: 1px solid #eee; padding-bottom: 3px; font-weight: bold;">Applicant Information</h3>
            <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
              <tr>
                <td style="width: 45%; padding: 2px 0;"><strong>Type:</strong></td>
                <td style="padding: 2px 0;" id="print-applicant-type"></td>
              </tr>
              <tr>
                <td style="padding: 2px 0;"><strong>Name:</strong></td>
                <td style="padding: 2px 0;" id="print-name"></td>
              </tr>
              <tr>
                <td style="padding: 2px 0;"><strong>Email:</strong></td>
                <td style="padding: 2px 0;" id="print-email"></td>
              </tr>
              <tr>
                <td style="padding: 2px 0;"><strong>Phone:</strong></td>
                <td style="padding: 2px 0;" id="print-phone"></td>
              </tr>
              <tr>
                <td style="padding: 2px 0;"><strong>Address:</strong></td>
                <td style="padding: 2px 0;" id="print-address"></td>
              </tr>
            </table>
          </td>
          <td style="width: 48%; vertical-align: top; padding-left: 2%;">
            <h3 style="font-size: 13px; margin-bottom: 6px; border-bottom: 1px solid #eee; padding-bottom: 3px; font-weight: bold;">Property Details</h3>
            <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
              <tr>
                <td style="width: 45%; padding: 2px 0;"><strong>Residence Type:</strong></td>
                <td style="padding: 2px 0;" id="print-residence-type"></td>
              </tr>
              <tr>
                <td style="padding: 2px 0;"><strong>Units:</strong></td>
                <td style="padding: 2px 0;" id="print-units"></td>
              </tr>
              <tr>
                <td style="padding: 2px 0;"><strong>Blocks:</strong></td>
                <td style="padding: 2px 0;" id="print-blocks"></td>
              </tr>
              <tr>
                <td style="padding: 2px 0;"><strong>Sections:</strong></td>
                <td style="padding: 2px 0;" id="print-sections"></td>
              </tr>
              <tr>
                <td style="padding: 2px 0;"><strong>File Number:</strong></td>
                <td style="padding: 2px 0;" id="print-file-number"></td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
      
      <!-- Property Address Section - Compact -->
      <div style="margin-bottom: 12px;">
        <h3 style="font-size: 13px; margin-bottom: 6px; border-bottom: 1px solid #eee; padding-bottom: 3px; font-weight: bold;">Property Address</h3>
        <table style="width: 100%; border-collapse: collapse;">
          <tr>
            <td style="width: 48%; vertical-align: top; padding-right: 2%;">
              <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
                <tr>
                  <td style="width: 35%; padding: 2px 0;"><strong>House No:</strong></td>
                  <td style="padding: 2px 0;" id="print-property-house-no"></td>
                </tr>
                <tr>
                  <td style="padding: 2px 0;"><strong>Plot No:</strong></td>
                  <td style="padding: 2px 0;" id="print-property-plot-no"></td>
                </tr>
                <tr>
                  <td style="padding: 2px 0;"><strong>Street:</strong></td>
                  <td style="padding: 2px 0;" id="print-property-street-name"></td>
                </tr>
              </table>
            </td>
            <td style="width: 48%; vertical-align: top; padding-left: 2%;">
              <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
                <tr>
                  <td style="width: 35%; padding: 2px 0;"><strong>District:</strong></td>
                  <td style="padding: 2px 0;" id="print-property-district"></td>
                </tr>
                <tr>
                  <td style="padding: 2px 0;"><strong>LGA:</strong></td>
                  <td style="padding: 2px 0;" id="print-property-lga"></td>
                </tr>
                <tr>
                  <td style="padding: 2px 0;"><strong>State:</strong></td>
                  <td style="padding: 2px 0;" id="print-property-state"></td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
        <div style="margin-top: 4px; font-size: 10px;">
          <strong>Complete Address:</strong> <span id="print-property-full-address"></span>
        </div>
      </div>
      
      <!-- Payment Information - Compact -->
      <div style="margin-bottom: 12px;">
        <h3 style="font-size: 13px; margin-bottom: 6px; border-bottom: 1px solid #eee; padding-bottom: 3px; font-weight: bold;">Payment Information</h3>
        <table style="width: 100%; border-collapse: collapse;">
          <tr>
            <td style="width: 48%; vertical-align: top; padding-right: 2%;">
              <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
                <tr>
                  <td style="width: 50%; padding: 2px 0;"><strong>Application Fee:</strong></td>
                  <td style="padding: 2px 0;" id="print-application-fee"></td>
                </tr>
                <tr>
                  <td style="padding: 2px 0;"><strong>Processing Fee:</strong></td>
                  <td style="padding: 2px 0;" id="print-processing-fee"></td>
                </tr>
                <tr>
                  <td style="padding: 2px 0;"><strong>Site Plan Fee:</strong></td>
                  <td style="padding: 2px 0;" id="print-site-plan-fee"></td>
                </tr>
              </table>
            </td>
            <td style="width: 48%; vertical-align: top; padding-left: 2%;">
              <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
                <tr>
                  <td style="width: 50%; padding: 2px 0;"><strong>Total:</strong></td>
                  <td style="padding: 2px 0; font-weight: bold;" id="print-total-fee"></td>
                </tr>
                <tr>
                  <td style="padding: 2px 0;"><strong>Receipt No:</strong></td>
                  <td style="padding: 2px 0;" id="print-receipt-number"></td>
                </tr>
                <tr>
                  <td style="padding: 2px 0;"><strong>Payment Date:</strong></td>
                  <td style="padding: 2px 0;" id="print-payment-date"></td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </div>
      
      <!-- Documents Section - Compact -->
      <div style="margin-bottom: 15px;">
        <h3 style="font-size: 13px; margin-bottom: 6px; border-bottom: 1px solid #eee; padding-bottom: 3px; font-weight: bold;">Uploaded Documents</h3>
        <div id="print-documents" style="font-size: 10px; line-height: 1.2;"></div>
      </div>
      
      <!-- Signature Section - Compact -->
      <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ccc;">
        <div style="text-align: center;">
          <div style="display: inline-block; width: 200px; border-bottom: 1px solid #000; margin-bottom: 5px;"></div>
          <div style="font-size: 10px;">Receiving Officer's Signature & Date</div>
        </div>
      </div>
    </div>
  </div>
