<?php

declare(strict_types=1);

namespace Ikuzo\SyliusBoxtalPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * @psalm-suppress UnusedVariable
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('boxtal');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->booleanNode('testmode')
                    ->defaultTrue()
                ->end()
                ->scalarNode('email')
                    ->defaultValue('test@ikuzo.fr')
                ->end()
                ->scalarNode('password')
                    ->defaultValue('change-me')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
