@page{margin:3mm 0 8mm}
.report-layout{width:100%;border:0;border-collapse:collapse;table-layout:fixed;font:inherit}
.report-layout>thead>tr>td,.report-layout>tbody>tr>td{padding:0;border:0;width:auto;text-align:left;vertical-align:top;background:transparent;font:inherit}
@media print{
    .page{width:100%!important;min-height:0!important;max-width:none!important;margin:0!important;box-shadow:none!important;break-after:auto!important}
    .report-layout>thead{display:table-header-group}
    .report-layout>tbody>tr,.report-layout>tbody>tr>td{break-inside:auto!important}
    .report-layout>thead>tr{break-inside:avoid;break-after:avoid}
    .table-scroll{overflow:visible!important}
    .footer{break-inside:avoid}
    thead{display:table-header-group}
    tr{break-inside:avoid}
}
