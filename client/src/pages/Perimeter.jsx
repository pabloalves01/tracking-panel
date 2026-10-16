import { useEffect, useState, useCallback } from 'react'
import Map, { useControl, Source, Layer } from 'react-map-gl/mapbox'
import MapboxDraw from '@mapbox/mapbox-gl-draw'
import 'mapbox-gl/dist/mapbox-gl.css'
import '@mapbox/mapbox-gl-draw/dist/mapbox-gl-draw.css'
import { createShipOperationPerimeter, getShipPerimeters } from '../lib/operations/shipOperations'

function DrawControl({ onCreate, onUpdate, onDelete }) {
    useControl(
        () =>
            new MapboxDraw({
                displayControlsDefault: false,
                controls: {
                    polygon: true,
                    trash: true,
                },
            }),
        ({ map }) => {
            map.on('draw.create', onCreate)
            map.on('draw.update', onUpdate)
            map.on('draw.delete', onDelete)
        },
        ({ map }) => {
            map.off('draw.create', onCreate)
            map.off('draw.update', onUpdate)
            map.off('draw.delete', onDelete)
        },
        { position: 'top-right' },
    )

    return null
}

export default function Perimeter() {
    const [polygons, setPolygons] = useState({})
    const [savedPerimeters, setSavedPerimeters] = useState({
        type: 'FeatureCollection',
        features: [],
    })
    const feature = Object.values(polygons)[0]

    const onUpdate = useCallback((event) => {
        setPolygons((current) => {
            const next = { ...current }
            for (const feature of event.features) {
                next[feature.id] = feature
            }
            return next
        })
    }, [])

    const onDelete = useCallback((event) => {
        setPolygons((current) => {
            const next = { ...current }
            for (const feature of event.features) {
                delete next[feature.id]
            }
            return next
        })
    }, [])

    useEffect(() => {
        handleGetShipPerimeters()
    }, [])

    async function handleGetShipPerimeters() {
        const response = await getShipPerimeters(1)
        setSavedPerimeters({
            type: 'FeatureCollection',
            features: response.map((row) => ({
                type: 'Feature',
                id: row.id,
                properties: { id: row.id },
                geometry: row.perimeter,
            })),
        })
    }

    async function handleCreateShipOperationPerimeter() {
        await createShipOperationPerimeter(1, feature.geometry)
    }

    return (
        <>
            <div className='flex gap-4 p-10'>
                <button className='bg-blue-500 text-white p-2 rounded-md' onClick={handleGetShipPerimeters}>Buscar Perimetros</button>
                <button className='bg-blue-500 text-white p-2 rounded-md' disabled={!feature} onClick={handleCreateShipOperationPerimeter}>Criar Perimetro</button>
            </div>
            <Map
                mapboxAccessToken={import.meta.env.VITE_MAPBOX_ACCESS_TOKEN}
                initialViewState={{
                    longitude: -48.6584315,
                    latitude: -28.2340501,
                    zoom: 14,
                }}
                style={{ width: '100%', height: '100vh' }}
                mapStyle="mapbox://styles/mapbox/standard"
            >
                <DrawControl
                    onCreate={onUpdate}
                    onUpdate={onUpdate}
                    onDelete={onDelete}
                />

                <Source id="saved-perimeters" type="geojson" data={savedPerimeters}>
                    <Layer
                        id="saved-perimeters-fill"
                        type="fill"
                        paint={{ 'fill-color': '#3b82f6', 'fill-opacity': 0.3 }}
                    />
                    <Layer
                        id="saved-perimeters-line"
                        type="line"
                        paint={{ 'line-color': '#3b82f6', 'line-width': 2 }}
                    />
                </Source>
            </Map>
        </>
    )
}