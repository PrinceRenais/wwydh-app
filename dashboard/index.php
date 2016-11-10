<?php

    session_start();

    include "../helpers/conn.php";
    include "../helpers/vars.php";

    if (!isset($_SESSION["user"])) {
        // user isn't logged in, redirect
        header("Location: ../home");
    }

    $id = $_SESSION["user"]["id"];

    $q = $conn->prepare("SELECT (
	(SELECT COUNT(p.id) * 100 FROM projects p INNER JOIN plans pl ON p.plan_id = pl.id INNER JOIN ideas i ON i.id = pl.idea_id INNER JOIN checklists c ON c.plan_id = pl.id INNER JOIN checklist_items ci ON ci.checklist_id = c.id AND ci.contributer_id = $id WHERE p.completed = 1) +
	(SELECT COUNT(pl.id) * 100 FROM plans pl INNER JOIN projects p ON p.plan_id = pl.id WHERE pl.creator_id = $id) +
	(SELECT COUNT(i.id) * 50 FROM ideas i INNER JOIN plans pl ON pl.idea_id = i.id INNER JOIN projects p ON p.plan_id = pl.id AND p.completed = 1 WHERE i.owner = $id) +
	(SELECT COUNT(pl.id) * 25 FROM plans pl INNER JOIN projects p ON p.plan_id = pl.id WHERE pl.creator_id = $id) +
	(SELECT COUNT(i.id) * 15 FROM ideas i INNER JOIN plans pl ON pl.idea_id = i.id INNER JOIN projects p ON p.plan_id = pl.id WHERE i.owner = $id) +
	(SELECT COUNT(i.id) * 5 FROM ideas i INNER JOIN plans pl ON pl.idea_id = i.id WHERE i.owner = 1 AND pl.published = $id) +
	(SELECT COUNT(up_i.id) * 2 FROM upvotes_ideas up_i INNER JOIN ideas i ON i.id = up_i.idea_id WHERE up_i.user_id = $id) +
	(SELECT COUNT(up_p.id) * 2 FROM upvotes_plans up_p INNER JOIN plans pl ON pl.id = up_p.plan_id WHERE up_p.user_id = $id)
) as points, (SELECT COUNT(p.id) FROM projects p WHERE p.leader_id = $id) AS `projects lead`,
(SELECT COUNT(ci.id) FROM projects p INNER JOIN plans pl ON p.plan_id = pl.id INNER JOIN ideas i ON i.id = pl.idea_id INNER JOIN checklists c ON c.plan_id = pl.id INNER JOIN checklist_items ci ON ci.checklist_id = c.id AND ci.contributer_id = $id WHERE p.completed = 1)
AS `projects contributed to`, (SELECT COUNT(p.id) AS count FROM projects p INNER JOIN plans pl ON p.plan_id = pl.id INNER JOIN ideas i ON i.id = pl.idea_id INNER JOIN checklists c ON c.plan_id = pl.id INNER JOIN checklist_items ci ON ci.checklist_id = c.id AND ci.contributer_id = $id WHERE p.completed = 1) AS `completed projects`,
(SELECT COUNT(m.id) FROM messages m WHERE m.to = $id AND m.read = 0) AS messages
");
    $q->execute();

    $data = $q->get_result()->fetch_array(MYSQLI_ASSOC);

    // get rank from data
    if ($data["projects lead"] > 0) $rank = "Project Leader";
    else if ($data["projects contributed to"] > 0) $rank = "Contributor";
    else $rank = "Beginner";

    $q = $conn->prepare("SELECT pl.title AS `plan title`, pl.*, l.*, i.* FROM plans pl LEFT JOIN locations l ON l.id = pl.location_id LEFT JOIN ideas i ON i.id = pl.idea_id WHERE pl.creator_id = $id");
    $q->execute();

    $plans_query = $q->get_result();

    // initialize arrays for plans
    $plans_ready = [];
    $plans_not_ready = [];
    $plans_published = [];

    while ($row = $plans_query->fetch_array(MYSQLI_ASSOC)) {
        // loop through, sort plans into correct arrays
        if ($row["published"] == 0) {
            // if its not published, sort into correct array
            if (isset($row["idea_id"]) && isset($row["location_id"])) array_push($plans_ready, $row);
            else array_push($plans_not_ready, $row);
        } else array_push($plans_published, $row);
    }
?>

<!DOCTYPE html>
<html>
    <head>
        <title>WWYDH Dashboard | <?php echo $_SESSION["user"]["first"] ?></title>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.0/jquery.min.js"></script>
        <script src="https://use.fontawesome.com/42543b711d.js"></script>
        <script src="../helpers/globals.js" type="text/javascript"></script>
        <script src="scripts.js" type="text/javascript"></script>
        <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,600i,700" rel="stylesheet">
        <link href="../helpers/header_footer.css" rel="stylesheet" type="text/css" />
        <link href="styles.css" rel="stylesheet" type="text/css" />

        <?php if (isset($_GET["newplan"])) { ?>
            <script type="text/javascript">
                plan = true;
            </script>
        <?php } ?>
    </head>
    <body>
        <div id="new-plan" class="overlay">
            <div class="wrapper">
                <div class="overlay_title">New Plan</div>
                <div class="overlay-inner">
                    <div class="label">Title</div>
                    <input type="text" name="plan-title" />
                    <div class="btn-group">
                        <div id="create-plan" class="plan-button"
                        <?php
                            if (isset($_GET["location"])) echo "data-location='{$_GET['location']}'";
                            if (isset($_GET["idea"])) echo "data-idea='{$_GET['idea']}'";
                        ?>
                        >Create Plan</div>
                        <div id="cancel-create-plan" class="plan-button">Cancel</div>
                    </div>
                </div>
            </div>
        </div>
        <div id="nav">
            <div class="nav-inner width clearfix <?php if (isset($_SESSION['user'])) echo 'loggedin' ?>">
                <a href="../home">
                    <div id="logo"></div>
                    <div id="logo_name">What Would You Do Here?</div>
                    <div class="spacer"></div>
                </a>
                <div id="user_nav" class="nav">
                    <?php if (!isset($_SESSION["user"])) { ?>
                        <ul>
                            <a href="../login"><li>Log in</li></a>
                            <a href="#"><li>Sign up</li></a>
                            <a href="../contact"><li>Contact</li></a>
                        </ul>
                    <?php } else { ?>
                        <div class="loggedin">
                            <span class="click-space">
                                <span class="chevron"><i class="fa fa-chevron-down" aria-hidden="true"></i></span>
                                <div class="image" style="background-image: url(../helpers/user_images/<?php echo $_SESSION["user"]["image"] ?>);"></div>
                                <span class="greet">Hi <?php echo $_SESSION["user"]["first"] ?>!</span>
                            </span>

                            <div id="nav_submenu">
                                <ul>
                                    <a href="../dashboard"><li>Dashboard</li></a>
                                    <a href="../profile"><li>My Profile</li></a>
                                    <a href="../helpers/logout.php?go=home"><li>Log out</li></a>
                                </ul>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <div id="main_nav" class="nav">
                    <ul>
                        <a href="../locations"><li>Locations</li></a>
                        <a href="../ideas"><li>Ideas</li></a>
                        <a href="../plans"><li>Plans</li></a>
                        <a href="../projects"><li>Projects</li></a>
                    </ul>
                </div>
            </div>
        </div>
        <div id="wrapper">
            <div id="content">
                <div class="inner">
                    <div id="overview" class="pane active">
                        <div id="user_intro" class="content-shadow">
                            <div id="counters">
                                <div id="points">
                                    <div class="counter_value"><?php echo $data["points"] ?></div>
                                    <div class="counter_label">Total Score</div>
                                </div>
                                <div id="completed_projects">
                                    <div class="counter_value"><?php echo $data["completed projects"] ?></div>
                                    <div class="counter_label">Completed Projects</div>
                                </div>
                            </div>
                            <div class="image" style="background-image: url(../helpers/user_images/<?php echo $_SESSION["user"]["image"] ?>)"></div>
                            <div class="name_rank">
                                <div id="name"><?php echo $_SESSION["user"]["first"]." ".$_SESSION["user"]["last"] ?></div>
                                <div id="rank"><?php echo $rank ?></div>
                            </div>
                            <div style="clear: both;"></div>
                        </div>
                    </div>
                    <div id="manage" class="pane">
                        <?php if (count($plans_ready) > 0) { ?>
                        <div id="plans-ready" class="manage-plans content-shadow">
                            <div class="sub-cat-title">Plans ready for publishing</div>
                            <div class="plans">
                                <?php foreach ($plans_ready as $p) { ?>
                                    <div class="plan">
                                        <div class="plan-inner">
                                            <div class="location_image" style="background-image: url(../helpers/location_images/<?php echo $p["image"] ?>)">
                                                <i class="fa <?php echo $location_categories[$p['category']]['fa-icon'] ?>"></i>
                                            </div>
                                            <div class="plan_title"><?php echo $p["plan title"] ?></div>
                                            <div class="idea_information">
                                                <div class="location_address"><?php echo $p["building_address"]." ".$p["city"].", Maryland ".$p["zip_code"] ?></div>
                                                <div class="idea_desc"><?php echo $p["description"] ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                                <div style="clear: both"></div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div id="sidebar">
                <div class="inner">
                    <ul>
                        <li data-target="overview" class="active"><i class="fa fa-tachometer" aria-hidden="true"></i>Overview</li>
                        <li data-target="inbox">
                            <i class="fa fa-inbox" aria-hidden="true"></i>
                            Inbox
                            <?php if ($data["messages"] > 0) { ?>
                                <div class="sidebar_badge"><?php echo $data["messages"] ?></div>
                            <?php } ?>
                        </li>
                        <li data-target="manage">
                            <i class="fa fa-wrench" aria-hidden="true"></i>
                            Manage
                            <?php if (count($plans_ready) > 0) { ?>
                                <div class="sidebar_badge"><?php echo count($plans_ready) ?></div>
                            <?php } ?>
                        </li>
                        <li><i class="fa fa-lightbulb-o" aria-hidden="true"></i>Your Entries</li>
                        <li>
                            <i class="fa fa-list" aria-hidden="true"></i>
                            Your List
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </body>
</html>
