<div class="navbar navbar-inverse set-radius-zero" >
        <div class="container">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand">

                    <img src="assets/img/logo.png" />
                </a>

            </div>

            <div class="right-div">
                <a href="logout.php" class="btn btn-danger pull-right">ลงชื่อออก</a>
            </div>
        </div>
    </div>
    <!-- LOGO HEADER END-->
    <section class="menu-section">
        <div class="container">
            <div class="row ">
                <div class="col-md-12">
                    <div class="navbar-collapse collapse ">
                        <ul id="menu-top" class="nav navbar-nav navbar-right">
                            <li><a href="dashboard.php" > หน้าแรก </a></li>
                           
                            <li>
                                <a href="#" class="dropdown-toggle" id="ddlmenuItem" data-toggle="dropdown"> ผู้รับผิดชอบ <i class="fa fa-angle-down"></i></a>
                                <ul class="dropdown-menu" role="menu" aria-labelledby="ddlmenuItem">
                                    <li role="presentation"><a role="menuitem" tabindex="-1" href="add-author.php">เพิ่มผู้รับผิดชอบ</a></li>
                                     <li role="presentation"><a role="menuitem" tabindex="-1" href="manage-authors.php">แก้ไขผู้รับผิดชอบ</a></li>
                                </ul>
                            </li>
                            
                            <li>
                                <a href="#" class="dropdown-toggle" id="ddlmenuItem" data-toggle="dropdown"> หมวดหมู่ <i class="fa fa-angle-down"></i></a>
                                <ul class="dropdown-menu" role="menu" aria-labelledby="ddlmenuItem">
                                    <li role="presentation"><a role="menuitem" tabindex="-1" href="add-category.php">เพิ่มหมวดหมู่</a></li>
                                     <li role="presentation"><a role="menuitem" tabindex="-1" href="manage-categories.php">แก้ไขหมวดหมู่</a></li>
                                </ul>
                            </li>



                            <li>
                                <a href="#" class="dropdown-toggle" id="ddlmenuItem" data-toggle="dropdown"> อุปกรณ์กีฬา <i class="fa fa-angle-down"></i></a>
                                <ul class="dropdown-menu" role="menu" aria-labelledby="ddlmenuItem">
                                    <li role="presentation"><a role="menuitem" tabindex="-1" href="add-book.php">เพิ่มอุปกรณ์กีฬา</a></li>
                                     <li role="presentation"><a role="menuitem" tabindex="-1" href="manage-books.php">แก้ไขอุปกรณ์กีฬา</a></li>
                                </ul>
                            </li>

                           <li>
                                <a href="#" class="dropdown-toggle" id="ddlmenuItem" data-toggle="dropdown"> ยืม/คืน อุปกรณ์กีฬา <i class="fa fa-angle-down"></i></a>
                                <ul class="dropdown-menu" role="menu" aria-labelledby="ddlmenuItem">
                                    <li role="presentation"><a role="menuitem" tabindex="-1" href="issue-book.php">ยืมอุปกรณ์กีฬา</a></li>
                                     <li role="presentation"><a role="menuitem" tabindex="-1" href="manage-issued-books.php">รายการ ยืม/คืน อุปกรณ์กีฬา</a></li>
                                </ul>
                            </li>
                             <li><a href="reg-students.php">สมาชิก</a></li>
                    
  <li><a href="change-password.php">เปลี่ยนรหัสผ่าน</a></li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </section>