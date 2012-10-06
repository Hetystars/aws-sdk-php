<?php

namespace Aws\Tests\DynamoDb\Session\LockingStrategy;

use Aws\Tests\DynamoDb\Session\AbstractSessionTestCase;
use Aws\DynamoDb\Session\LockingStrategy\PessimisticLockingStrategy;

class PessimisticLockingStrategyTest extends AbstractSessionTestCase
{
    /**
     * @covers Aws\DynamoDb\Session\LockingStrategy\PessimisticLockingStrategy::doRead
     * @covers Aws\DynamoDb\Session\LockingStrategy\PessimisticLockingStrategy::__construct
     */
    public function testDoReadWorksCorrectly()
    {
        // Prepare mocks
        $client  = $this->getMockedClient();
        $config  = $this->getMockedConfig();
        $command = $this->getMockedCommand($client);

        $config->expects($this->any())
            ->method('get')
            ->will($this->returnCallback(function ($key) {
                return ($key === 'max_lock_wait_time') ? 10 : null;
            }));

        $command->expects($this->any())
            ->method('execute')
            ->will($this->returnCallback(function () {
                static $calls = 0;

                // Simulate lock acquisition failures
                if ($calls++ < 5) {
                    throw new \Aws\DynamoDb\Exception\ConditionalCheckFailedException;
                } else {
                    return array(
                        'Attributes' => array(
                            'foo' => array(
                                'S' => 'bar'
                            )
                        ),
                    );
                }
            }));

        // Test the doRead method
        $strategy = new PessimisticLockingStrategy($client, $config);
        $item = $strategy->doRead('test');
        $this->assertSame(array('foo' => 'bar'), $item);
    }

    /**
     * @covers Aws\DynamoDb\Session\LockingStrategy\PessimisticLockingStrategy::doRead
     * @expectedException Aws\DynamoDb\Exception/AccessDeniedException
     */
    public function testReadFailsForOther400Errors()
    {
        // Prepare mocks
        $client  = $this->getMockedClient();
        $config  = $this->getMockedConfig();
        $command = $this->getMockedCommand($client);
        $command->expects($this->any())
            ->method('execute')
            ->will($this->throwException(new \Aws\DynamoDb\Exception\AccessDeniedException()));

        // Test the doRead method
        $strategy = new PessimisticLockingStrategy($client, $config);
        $strategy->doRead('test');
    }
}
