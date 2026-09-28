<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventReminder;

/**
 * Decides who receives the event reminder email. Pure logic, no database or framework access:
 * the caller loads the persons (see SendEventReminderHandler), this class only applies the rules.
 *
 * - main instructor: the flagged one, otherwise the first instructor
 * - co-instructors: all instructors except the main instructor
 * - contact (To/Reply-To): the registration coordinator if set, otherwise the main instructor
 * - CC: all instructors except the contact
 * - BCC: the participants, minus anyone already in To or CC
 */
final class RecipientResolver
{
    /**
     * @param list<Person> $instructors All instructors of the event, main instructor first
     */
    public function getMainInstructor(array $instructors, Person|null $flaggedMainInstructor): Person|null
    {
        if (null !== $flaggedMainInstructor && $flaggedMainInstructor->hasEmail()) {
            return $flaggedMainInstructor;
        }

        foreach ($instructors as $instructor) {
            if ($instructor->hasEmail()) {
                return $instructor;
            }
        }

        return null;
    }

    /**
     * @param list<Person> $instructors
     *
     * @return list<Person>
     */
    public function getCoInstructors(array $instructors, Person|null $mainInstructor): array
    {
        if (null === $mainInstructor) {
            return array_values($instructors);
        }

        return array_values(array_filter(
            $instructors,
            static fn (Person $instructor): bool => $instructor->id !== $mainInstructor->id,
        ));
    }

    public function getContact(Person|null $mainInstructor, Person|null $registrationCoordinator): Person|null
    {
        if (null !== $registrationCoordinator && $registrationCoordinator->hasEmail()) {
            return $registrationCoordinator;
        }

        return $mainInstructor;
    }

    /**
     * @param list<Person> $instructors
     * @param list<string> $participantEmails
     */
    public function resolve(Person|null $contact, array $instructors, array $participantEmails): Recipients
    {
        $to = null !== $contact ? $this->normalize($contact->email) : '';

        $cc = $this->uniqueEmails(
            array_map(static fn (Person $instructor): string => $instructor->email, $instructors),
            ['' !== $to ? $to : null],
        );

        $bcc = $this->uniqueEmails($participantEmails, [$to, ...$cc]);

        return new Recipients($to, $cc, $bcc);
    }

    /**
     * Lowercase, trim, drop empty addresses and duplicates, drop everything listed in $exclude.
     *
     * @param list<string>      $emails
     * @param list<string|null> $exclude
     *
     * @return list<string>
     */
    private function uniqueEmails(array $emails, array $exclude = []): array
    {
        $exclude = array_filter(array_map(fn (string|null $email): string => null === $email ? '' : $this->normalize($email), $exclude));

        $result = [];

        foreach ($emails as $email) {
            $email = $this->normalize($email);

            if ('' === $email || \in_array($email, $exclude, true) || \in_array($email, $result, true)) {
                continue;
            }

            $result[] = $email;
        }

        return $result;
    }

    private function normalize(string $email): string
    {
        return strtolower(trim($email));
    }
}
