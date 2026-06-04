<?php

class DiagEmailAccounts extends Module {
    var $prologue = 'Diagnose enabled email account fetch configurations';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'summary' => 'Display fetch configuration summary for enabled mailboxes',
            ),
        ),
    );

    var $autohelp = false;

    function run($args, $options) {
        Bootstrap::connect();
        $ost = osTicket::start();

        if (!$args['action']) {
            $this->stderr->write("Error: action required\n\n");
            $this->showHelp();
            return;
        }

        switch (strtolower($args['action'])) {
        case 'summary':
            $this->showSummary();
            break;
        default:
            $this->stderr->write("Error: unknown action '{$args['action']}'\n\n");
            $this->showHelp();
        }
    }

    function showSummary() {
        $mailboxes = MailBoxAccount::objects()
            ->filter(array('active' => 1))
            ->select_related('email');

        if ($mailboxes->count() == 0) {
            $this->stdout->write("No enabled mailbox accounts found.\n");
            return;
        }

        $this->stdout->write(sprintf("Found %d enabled mailbox account(s):\n\n", $mailboxes->count()));

        $this->printTableHeader();

        $i = 0;
        foreach ($mailboxes as $mailbox) {
            $email = $mailbox->getEmail();
            $emailAddress = $email ? $email->getAddress() : 'N/A';
            $fetchFolder = $mailbox->getFetchFolder() ?: 'INBOX';
            $archiveFolder = $mailbox->getArchiveFolder() ?: 'N/A';
            $maxFetch = $mailbox->getMaxFetch() ?: 30;
            $deleteEmails = $mailbox->canDeleteEmails() ? 'Yes' : 'No';

            $this->printTableRow(
                $emailAddress,
                $fetchFolder,
                $archiveFolder,
                $maxFetch,
                $deleteEmails
            );
            $i++;
        }

        $this->printTableFooter();
    }

    function printTableHeader() {
        $width = $this->getColumnWidths();
        $fmt = "| %-${width['email']}s | %-${width['fetch']}s | %-${width['archive']}s | %-${width['max']}s | %-${width['delete']}s |\n";

        $this->stdout->write(sprintf($fmt, 'Email Address', 'Fetch Folder', 'Archive Folder', 'Max Fetch', 'Delete Emails'));
        $this->stdout->write($this->getSeparatorLine($width));
    }

    function printTableRow($email, $fetchFolder, $archiveFolder, $maxFetch, $deleteEmails) {
        $width = $this->getColumnWidths();
        $fmt = "| %-${width['email']}s | %-${width['fetch']}s | %-${width['archive']}s | %-${width['max']}s | %-${width['delete']}s |\n";

        $this->stdout->write(sprintf($fmt, $email, $fetchFolder, $archiveFolder, $maxFetch, $deleteEmails));
    }

    function printTableFooter() {
        $width = $this->getColumnWidths();
        $this->stdout->write($this->getSeparatorLine($width));
    }

    function getColumnWidths() {
        return array(
            'email' => 30,
            'fetch' => 15,
            'archive' => 15,
            'max' => 10,
            'delete' => 12,
        );
    }

    function getSeparatorLine($width) {
        $sep = '+';
        foreach ($width as $w) {
            $sep .= '-' . str_repeat('-', $w) . '-+';
        }
        return $sep . "\n";
    }
}

Module::register('diag', 'DiagEmailAccounts');
?>
