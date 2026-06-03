<?php
global $thisstaff;

$role = $ticket->getRole($thisstaff);

$taskStatusFilter = isset($_REQUEST['task_status']) ? $_REQUEST['task_status'] : 'all';
if (!in_array($taskStatusFilter, array('all', 'open', 'closed'))) {
    $taskStatusFilter = 'all';
}

$tasks = Task::objects()
    ->select_related('dept', 'staff', 'team')
    ->order_by('-created');

$tasks->filter(array(
            'object_id' => $ticket->getId(),
            'object_type' => 'T'));

if ($taskStatusFilter === 'open') {
    $tasks->filter(array('status__state' => 'open'));
} elseif ($taskStatusFilter === 'closed') {
    $tasks->filter(array('status__state' => 'closed'));
}

$count = $tasks->count();
$pageNav = new Pagenate($count,1, 100000); //TODO: support ajax based pages
$showing = $pageNav->showing().' '._N('task', 'tasks', $count);

$callback_url = sprintf('ajax.php/tickets/%d/tasks?task_status=%s',
    $ticket->getId(), $taskStatusFilter);
?>
<div id="tasks_content" style="display:block;">
<div class="pull-left">
   <?php
    $filter_links = array();
    $filter_labels = array(
        'all' => __('All'),
        'open' => __('Open'),
        'closed' => __('Closed'),
    );
    foreach ($filter_labels as $f => $label) {
        $qs = array('task_status' => $f);
        $active = ($taskStatusFilter === $f) ? 'active' : '';
        $filter_links[] = sprintf(
            '<a href="#tickets/%d/tasks?%s" class="button %s task-status-filter" data-status="%s">%s</a>',
            $ticket->getId(),
            http_build_query($qs),
            $active,
            $f,
            $label
        );
    }
    echo implode(' ', $filter_links);
    ?>
    &nbsp;&nbsp;
   <?php
    if ($count) {
        echo '<strong>'.$showing.'</strong>';
    } else {
        echo sprintf(__('%s does not have any tasks'), $ticket? __('This ticket') :
                __('System'));
    }
   ?>
</div>
<div class="pull-right">
    <?php
    if ($role && $role->hasPerm(Task::PERM_CREATE)) { ?>
        <a
        class="green button action-button ticket-task-action"
        data-url="tickets.php?id=<?php echo $ticket->getId(); ?>#tasks"
        data-dialog-config='{"size":"large"}'
        href="#tickets/<?php
            echo $ticket->getId(); ?>/add-task">
            <i class="icon-plus-sign"></i> <?php
            print __('Add New Task'); ?></a>
    <?php
    }
    $allTasks = Task::objects()
        ->filter(array('object_id' => $ticket->getId(), 'object_type' => 'T'));
    foreach ($allTasks as $task)
        $batchTaskStatus .= $task->isOpen() ? 'open' : 'closed';

    if ($count)
        Task::getAgentActions($thisstaff, array(
                    'container' => '#tasks_content',
                    'callback_url' => $callback_url,
                    'morelabel' => __('Options'),
                    'status' => $batchTaskStatus ? $batchTaskStatus : '')
                );
    ?>
</div>
<div class="clear"></div>
<div>
<?php
if ($count) { ?>
<form action="#tickets/<?php echo $ticket->getId(); ?>/tasks" method="POST"
    name='tasks' id="tasks" style="padding-top:7px;">
<?php csrf_token(); ?>
 <input type="hidden" name="a" value="mass_process" >
 <input type="hidden" name="do" id="action" value="" >
 <table class="list" border="0" cellspacing="1" cellpadding="2" width="940">
    <thead>
        <tr>
            <?php
            if (1) {?>
            <th width="8px">&nbsp;</th>
            <?php
            } ?>
            <th width="70"><?php echo __('Number'); ?></th>
            <th width="100"><?php echo __('Date'); ?></th>
            <th width="100"><?php echo __('Status'); ?></th>
            <th width="300"><?php echo __('Title'); ?></th>
            <th width="200"><?php echo __('Department'); ?></th>
            <th width="200"><?php echo __('Assignee'); ?></th>
        </tr>
    </thead>
    <tbody class="tasks">
    <?php
    foreach($tasks as $task) {
        $id = $task->getId();
        $access = $task->checkStaffPerm($thisstaff);
        $assigned='';
        if ($task->staff || $task->team) {
            $assigneeType = $task->staff ? 'staff' : 'team';
            $icon = $assigneeType == 'staff' ? 'staffAssigned' : 'teamAssigned';
            $assigned=sprintf('<span class="Icon %s">%s</span>',
                    $icon,
                    Format::truncate($task->getAssigned(),40));
        }

        $status = $task->isOpen() ? '<strong>'.__('Open').'</strong>': __('Closed');

        $title = Format::htmlchars(Format::truncate($task->getTitle(),40));
        $threadcount = $task->getThread() ?
            $task->getThread()->getNumEntries() : 0;

        if ($access)
            $viewhref = sprintf('#tickets/%d/tasks/%d/view', $ticket->getId(), $id);
        else
            $viewhref = '#';

        ?>
        <tr id="<?php echo $id; ?>">
            <td align="center" class="nohover">
                <input class="ckb" type="checkbox" name="tids[]"
                value="<?php echo $id; ?>" <?php echo $sel?'checked="checked"':''; ?>>
            </td>
            <td align="center" nowrap>
              <a class="Icon no-pjax preview"
                title="<?php echo __('Preview Task'); ?>"
                href="<?php echo $viewhref; ?>"
                data-preview="#tasks/<?php echo $id; ?>/preview"
                ><?php echo $task->getNumber(); ?></a></td>
            <td align="center" nowrap><?php echo
            Format::datetime($task->created); ?></td>
            <td><?php echo $status; ?></td>
            <td>
                <?php
                if ($access) { ?>
                    <a <?php if ($flag) { ?> class="no-pjax"
                        title="<?php echo ucfirst($flag); ?> Task" <?php } ?>
                        href="<?php echo $viewhref; ?>"><?php
                    echo $title; ?></a>
                 <?php
                } else {
                     echo $title;
                }
                    if ($threadcount>1)
                        echo "<small>($threadcount)</small>&nbsp;".'<i
                            class="icon-fixed-width icon-comments-alt"></i>&nbsp;';
                    if ($row['collaborators'])
                        echo '<i class="icon-fixed-width icon-group faded"></i>&nbsp;';
                    if ($row['attachments'])
                        echo '<i class="icon-fixed-width icon-paperclip"></i>&nbsp;';
                ?>
            </td>
            <td><?php echo Format::truncate($task->dept->getName(), 40); ?></td>
            <td>&nbsp;<?php echo $assigned; ?></td>
        </tr>
   <?php
    }
    ?>
    </tbody>
</table>
</form>
<?php
 } ?>
</div>
</div>
<div id="task_content" style="display:none;">
</div>
<script type="text/javascript">
$(function() {
    var currentFilter = '<?php echo $taskStatusFilter; ?>';

    $(document).off('click.task-status-filter');
    $(document).on('click.task-status-filter', 'a.task-status-filter', function(e) {
        e.preventDefault();
        var status = $(this).data('status');
        currentFilter = status;
        var url = 'ajax.php/tickets/<?php echo $ticket->getId(); ?>/tasks?task_status=' + status;
        $.pjax({url: url, container: '#tasks_content', push: false, timeout: 30000});
        return false;
    });

    $(document).off('click.taskv');
    $(document).on('click.taskv', 'tbody.tasks a, a#reload-task', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        if ($(this).attr('href').length > 1) {
            var url = 'ajax.php/'+$(this).attr('href').substr(1);
            var $container = $('div#task_content');
            var $stop = $('ul#ticket_tabs').offset().top;
            $.pjax({url: url, container: 'div#task_content', push: false, scrollTo: $stop})
            .done(
                function() {
                $container.show();
                $('.tip_box').remove();
                $('div#tasks_content').hide();
                });
        } else {
            $(this).trigger('mouseenter');
        }

        return false;
     });
    // Ticket Tasks
    $(document).off('.ticket-task-action');
    $(document).on('click.ticket-task-action', 'a.ticket-task-action', function(e) {
        e.preventDefault();
        var url = 'ajax.php/'
        +$(this).attr('href').substr(1)
        +'?_uid='+new Date().getTime();
        var $redirect = $(this).data('href');
        var $options = $(this).data('dialogConfig');
        $.dialog(url, [201], function (xhr) {
            var tid = parseInt(xhr.responseText);
            if (tid) {
                var url = 'ajax.php/tickets/'+<?php echo $ticket->getId();
                ?>+'/tasks';
                var $container = $('div#task_content');
                $container.load(url+'/'+tid+'/view', function () {
                    $('.tip_box').remove();
                    $('div#tasks_content').hide();
                    $.pjax({url: url+'?task_status='+currentFilter, container: '#tasks_content', timeout: 30000, push: false});
                }).show();
            } else {
                window.location.href = $redirect ? $redirect : window.location.href;
            }
        }, $options);
        return false;
    });

    $('#ticket-tasks-count').html(<?php echo $count; ?>);
});
</script>
