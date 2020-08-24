<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2020
 *
 * This program is a free software product.
 * You can redistribute it and/or modify it under the terms of the GNU Affero General Public License
 * (AGPL) version 3 as published by the Free Software Foundation.
 * In accordance with Section 7(a) of the GNU AGPL its Section 15 shall be amended to the effect
 * that Ascensio System SIA expressly excludes the warranty of non-infringement of any third-party rights.
 *
 * This program is distributed WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * For details, see the GNU AGPL at: http://www.gnu.org/licenses/agpl-3.0.html
 *
 * You can contact Ascensio System SIA at 20A-12 Ernesta Birznieka-Upisha street, Riga, Latvia, EU, LV-1050.
 *
 * The interactive user interfaces in modified source and object code versions of the Program
 * must display Appropriate Legal Notices, as required under Section 5 of the GNU AGPL version 3.
 *
 * Pursuant to Section 7(b) of the License you must retain the original Product logo when distributing the program.
 * Pursuant to Section 7(e) we decline to grant you any rights under trademark law for use of our trademarks.
 *
 * All the Product's GUI elements, including illustrations and icon sets, as well as technical
 * writing content are licensed under the terms of the Creative Commons Attribution-ShareAlike 4.0 International.
 * See the License terms at http://creativecommons.org/licenses/by-sa/4.0/legalcode
 *
 */
?>

<style type="text/css">
    #body-public #content {
        min-height: 100%;
    }

    .loadmask{left:0;top:0;position:absolute;height:100%;width:100%;overflow:hidden;border:none;background-color:#f4f4f4;z-index:1001;}
    .loader-page{width:100%;height:170px;bottom:42%;position:absolute;text-align:center;line-height:10px;margin-bottom:20px;}
    .loader-page-romb{width:40px;display:inline-block;}
    .romb{width:40px;height:40px;position:absolute;background:red;border-radius:6px;
    -webkit-transform:rotate(135deg) skew(20deg,20deg);
    -moz-transform:rotate(135deg) skew(20deg,20deg);
    -ms-transform:rotate(135deg) skew(20deg,20deg);
    -o-transform:rotate(135deg) skew(20deg,20deg);
    -webkit-animation:movedown 3s infinite ease;
    -moz-animation:movedown 3s infinite ease;
    -ms-animation:movedown 3s infinite ease;
    -o-animation:movedown 3s infinite ease;
    animation:movedown 3s infinite ease;
    }
    #blue{z-index:3;background:#55bce6;
    -webkit-animation-name:blue;
    -moz-animation-name:blue;
    -ms-animation-name:blue;
    -o-animation-name:blue;
    animation-name:blue;
    }
    #red{z-index:1;background:#de7a59;
    -webkit-animation-name:red;
    -moz-animation-name:red;
    -ms-animation-name:red;
    -o-animation-name:red;
    animation-name:red;
    }
    #green{z-index:2;background:#a1cb5c;
    -webkit-animation-name:green;
    -moz-animation-name:green;
    -ms-animation-name:green;
    -o-animation-name:green;
    animation-name:green;
    }
    @-webkit-keyframes red{
    0%{top:120px;background:#de7a59;}
    10%{top:120px;background:#F2CBBF;}
    14%{background:#f4f4f4;top:120px;}
    15%{background:#f4f4f4;top:0;}
    20%{background:#E6E4E4;}
    30%{background:#D2D2D2;}
    40%{top:120px;}
    100%{top:120px;background:#de7a59;}
    }
    @keyframesred{
    0%{top:120px;background:#de7a59;}
    10%{top:120px;background:#F2CBBF;}
    14%{background:#f4f4f4;top:120px;}
    15%{background:#f4f4f4;top:0;}
    20%{background:#E6E4E4;}
    30%{background:#D2D2D2;}
    40%{top:120px;}
    100%{top:120px;background:#de7a59;}
    }
    @-webkit-keyframes green{
    0%{top:110px;background:#a1cb5c;opacity:1;}
    10%{top:110px;background:#CBE0AC;opacity:1;}
    14%{background:#f4f4f4;top:110px;opacity:1;}
    15%{background:#f4f4f4;top:0;opacity:1;}
    20%{background:#f4f4f4;top:0;opacity:0;}
    25%{background:#EFEFEF;top:0;opacity:1;}
    30%{background:#E6E4E4;}
    70%{top:110px;}
    100%{top:110px;background:#a1cb5c;}
    }
    @keyframes green{
    0%{top:110px;background:#a1cb5c;opacity:1;}
    10%{top:110px;background:#CBE0AC;opacity:1;}
    14%{background:#f4f4f4;top:110px;opacity:1;}
    15%{background:#f4f4f4;top:0;opacity:1;}
    20%{background:#f4f4f4;top:0;opacity:0;}
    25%{background:#EFEFEF;top:0;opacity:1;}
    30%{background:#E6E4E4;}
    70%{top:110px;}
    100%{top:110px;background:#a1cb5c;}
    }
    @-webkit-keyframes blue{
    0%{top:100px;background:#55bce6;opacity:1;}
    10%{top:100px;background:#BFE8F8;opacity:1;}
    14%{background:#f4f4f4;top:100px;opacity:1;}
    15%{background:#f4f4f4;top:0;opacity:1;}
    20%{background:#f4f4f4;top:0;opacity:0;}
    25%{background:#f4f4f4;top:0;opacity:0;}
    45%{background:#EFEFEF;top:0;opacity:0.2;}
    100%{top:100px;background:#55bce6;}
    }
    @keyframes blue{
    0%{top:100px;background:#55bce6;opacity:1;}
    10%{top:100px;background:#BFE8F8;opacity:1;}
    14%{background:#f4f4f4;top:100px;opacity:1;}
    15%{background:#f4f4f4;top:0;opacity:1;}
    20%{background:#f4f4f4;top:0;opacity:0;}
    25%{background:#fff;top:0;opacity:0;}
    45%{background:#EFEFEF;top:0;opacity:0.2;}
    100%{top:100px;background:#55bce6;}
    }
</style>
<div class="loadmask"><div class="loader-page"><div class="loader-page-romb"><div class="romb" id="blue"></div><div class="romb" id="green"></div><div class="romb" id="red"></div></div></div></div>
